<?php

namespace Omnibridge\Symfony\Omnibase;

use App\Entity\User;
use Base\Entity\User\Notification;
use Base\Notifier\Mail\EmailComposer;
use Base\Notifier\Abstract\BaseNotifierInterface;
use Base\Notifier\Recipient\Recipient;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bridge\Twig\Mime\WrappedTemplatedEmail;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\Notifier\Message\EmailMessage;
use Symfony\Component\Notifier\Recipient\EmailRecipientInterface;
use Symfony\Component\Notifier\Recipient\NoRecipient;
use Throwable;
use Omnibridge\Contract\Views;
use Omnibridge\Twig\TwigViews;
use Twig\Environment;
use UnexpectedValueException;

/**
 * The Symfony half of Base\Entity\User\Notification (plan "omnibase
 * agnostique", step C3f): what the entity made with the framework, moved
 * here as it was. The entity keeps what a notification is - its subject,
 * content, importance, channels, recipients, context, templates - and its
 * sending through the notifier; it hands this class what needs Symfony:
 *
 * - throwable(): the error an ExceptionEvent (HttpKernel) carries, or a
 *   FlattenException (ErrorHandler), made the notification's content;
 * - response(): render()'s HttpFoundation Response;
 * - email(): asEmailMessage(), the NotificationEmail of Symfony's Twig bridge
 *   (TemplatedEmail, WrappedTemplatedEmail), its attachments
 *   (HttpFoundation's UploadedFile), its HTML through Twig.
 *
 * Same names for what the sites use: they keep calling the entity's
 * methods, which answer what they answered.
 */
final class NotificationMessages
{
    /** The e-mail frame: the HTML of a notification whose template is missing or not found. */
    public const FRAME = EmailComposer::FRAME;

    /**
     * The error to describe, when the notification's content is one: an ExceptionEvent's throwable,
     * a FlattenException, a Throwable; null for anything else.
     */
    public static function throwable(mixed $content): Throwable|FlattenException|null
    {
        if ($content instanceof ExceptionEvent) {
            return $content->getThrowable();
        }

        return $content instanceof FlattenException || $content instanceof Throwable ? $content : null;
    }

    public static function response(string $html): Response
    {
        return new Response($html);
    }

    public static function email(Notification $notification, EmailRecipientInterface $recipient, BaseNotifierInterface $notifier, Views|Environment $views, string $projectDir): EmailMessage
    {
        // Without Symfony's Twig bridge (a host that is not Symfony: Laravel), or views that are not Twig's: the
        // e-mail EmailComposer writes the same way under every host (plan "omnibase agnostic", F5c).
        if (!class_exists(TemplatedEmail::class) || !($views instanceof Environment || $views instanceof TwigViews)) {
            return self::composed($notification, $recipient, $notifier, $views);
        }

        // Symfony's templated e-mail is Twig's (WrappedTemplatedEmail): the environment behind the views (step F5a).
        $twig = $views instanceof Environment ? $views : ($views instanceof TwigViews ? $views->environment() : throw new \LogicException('Symfony\'s notification e-mail is rendered by Twig: the views are '.get_debug_type($views).', not omnibridge/twig\'s TwigViews.'));
        $views = $views instanceof Views ? $views : new TwigViews($views);
        $message = EmailMessage::fromNotification($notification, $recipient);

        /**
         * @var EmailRecipientInterface $technicalRecipient
         */
        $technicalRecipient = $notifier->getTechnicalRecipient();
        if ($technicalRecipient instanceof NoRecipient) {
            throw new UnexpectedValueException("No support address found.");
        }

        $title = $notification->getTitle();
        $content = $notification->getContent();
        $footer = $notification->getFooter();

        $fwd = "";
        $subject = $notification->getSubject();
        $from = $technicalRecipient->getEmail();
        $to = $recipient->getEmail();

        if ($notification->isMarkAsAdmin()) {
            $user = $notification->getUser() ?? "User \"" . User::getIp() . "\"";
            $fwd .= "Fwd: ";
            $title = $notifier->getTranslator()->trans("@emails.admin_forwarding.notice", [$user, $notification->getTitle()]);
            $content = $notification->getContent();
        }

        if ($notifier->isTest($recipient)) {

            // A test address (base.notifier.technical_loopback, test_recipients): Base\Notifier\Mail\EmailComposer's rule.
            $trans = fn (string $id): string => $notifier->getTranslator()->trans($id);
            $fwd .= EmailComposer::TEST_PREFIX;
            $to = EmailComposer::testAddress($technicalRecipient->getEmail(), $recipient->getEmail(), $trans);

            $footer = EmailComposer::testFooter($footer, $trans);

            $htmlFooter = $notification->getHtmlParameters()["footer_text"] ?? null;
            if ($htmlFooter) {

                $htmlFooter = EmailComposer::testFooter($htmlFooter, $trans);
                $notification->addHtmlParameter("footer_text", $htmlFooter);
            }
        }

        /**
         * @var TemplatedEmail $email
         */
        $email = $message->getMessage(); // Embed image inside email (cid:/)
        $context = array_merge([

            // render html
            "raw" => true,
            // Make sure notification context for html template got images pre-rendered
            "warmup" => true, // e.g. when sending emails..

        ], $notification->getContext([
            "title" => $title,
            "content" => $content,
            "footer_text" => $footer,
            "recipient" => $recipient
        ]));

        if (array_key_exists("email", $notification->getHtmlParameters())) {
            throw new UnexpectedValueException("`email` is a reserved key. Cannot pass it through html parameters.");
        }

        $htmlParameters = array_merge(
            $context,
            $notification->getHtmlParameters(),
            ["email" => new WrappedTemplatedEmail($twig, $email)]
        );

        // Append notification attachments
        $importance = $context["importance"] ?? $notification->getImportance();
        $attachments = array_unique(array_merge($notification->getAttachments(), $context["attachments"] ?? []));

        $context["attachments"] = [];
        foreach ($attachments as $key => $attachment) {

            if (!$attachment instanceof UploadedFile) {
                continue;
            }

            $email->embed($attachment->getContent(), $attachment->getClientOriginalName());
            $context["attachments"][] = $attachment->getClientOriginalName();
        }

        // Fallback: Append cid:/ like attachments
        foreach ($context as $value) {

            if (!$value) {
                continue;
            }
            if (!is_string($value)) {
                continue;
            }
            if (!str_starts_with($value, "cid:")) {
                continue;
            }

            list($cid, $path) = explode(":", $value);
            $email->embed(fopen($projectDir . "/" . $path, 'rb'), $path /* replace by alias */);
        }

        // The template in the e-mail frame, the subject its <title> gives (Base\Notifier\Mail\EmailComposer:
        // the same lines under every host).
        $rendered = EmailComposer::render($views, $notification->getHtmlTemplate(), $htmlParameters);
        $context["html"] = $rendered["html"];
        $subject = $rendered["subject"] ?? $subject;

        $subject ??= $notification->getSubject();
        $subject = $fwd . $subject;

        // Alias cid://
        $context["html"] = EmailComposer::alias($email, $context["html"]);

        // Priority
        $priority = [Notification::IMPORTANCE_HIGH, Notification::IMPORTANCE_MEDIUM, Notification::IMPORTANCE_LOW];
        $email
            ->importance(in_array($importance, $priority) ? $importance : "")
            ->subject($subject)
            ->from($from)
            ->to($to)
            ->htmlTemplate("@Base/notifier/raw.html.twig")
            ->context($context);

        if ($context["replyTo"] ?? null) {
            $email->replyTo($context["replyTo"]);
        }

        return $message;
    }

    /**
     * A notification's e-mail without Symfony's Twig bridge: EmailComposer::compose(), from the technical
     * address, to the recipient or the technical address for a test one, a plain symfony/mime Email in
     * the Notifier's EmailMessage. Forwarded to the administrators, it says so as Symfony's does.
     */
    public static function composed(Notification $notification, EmailRecipientInterface $recipient, BaseNotifierInterface $notifier, Views|Environment $views): EmailMessage
    {
        $technical = $notifier->getTechnicalRecipient();
        if ($technical instanceof NoRecipient || !$technical instanceof EmailRecipientInterface) {
            throw new UnexpectedValueException("No support address found.");
        }
        $translator = $notifier->getTranslator();
        $composer = new EmailComposer(
            $views,
            $technical->getEmail(),
            static fn (string $to): bool => $recipient instanceof Recipient && $notifier->isTest($recipient),
            static fn (string $id): string => $translator->trans($id),
        );

        $subject = (string) $notification->getSubject();
        $content = (string) $notification->getContent();
        if ($notification->isMarkAsAdmin()) {
            $user = $notification->getUser() ?? "User \"" . User::getIp() . "\"";
            $subject = "Fwd: " . $subject;
            $content = $translator->trans("@emails.admin_forwarding.notice", [$user, $notification->getTitle()]);
        }

        $parameters = $notification->getContext(["title" => $notification->getTitle(), "content" => $content, "footer_text" => $notification->getFooter(), "recipient" => $recipient]);
        $email = $composer->compose($recipient->getEmail(), $notification->getHtmlTemplate(), array_merge($parameters, $notification->getHtmlParameters()), $content, $subject, $recipient);

        return new EmailMessage($email);
    }
}
