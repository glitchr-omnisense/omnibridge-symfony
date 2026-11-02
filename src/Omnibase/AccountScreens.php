<?php

namespace Omnibridge\Symfony\Omnibase;

use Base\Entity\User;
use Base\Entity\User\Notification;
use Base\Screen\Account\NotificationsPage;
use Base\Screen\Account\ProfilePage;
use Base\Screen\Account\SettingsPage;
use Base\Screen\Builder\Accounts;
use Omnibridge\Contract\Access;
use Twig\Environment;
use Twig\Extension\CoreExtension;

/**
 * The account's screens under Symfony (plan "omnibase agnostique", C5): what
 * the framework-free builder (Base\Screen\Builder\Accounts) cannot know, asked
 * where the templates of before asked it - the viewer and their roles (the
 * Access contract: `app.user`, `is_granted`), an avatar's thumbnail
 * (`|thumbnail`), the providers one may link (`oauth_available()`), web push
 * and the back-office address of a notification (`push_available()`,
 * `push_vapid_key()`, `notification_admin_url()`), Twig's time zone (the days
 * of the notifications' groups) -, handed to the builder. What the account's
 * controllers and the Twig function `account_profile()` (a host page's) call;
 * a Twig runtime, so that it may read Twig's functions and filters without a
 * circular service. It asks them by their names, as a template does, not by
 * the classes of omnibase's Twig extensions (plan "omnibase agnostic", F4:
 * nothing outside src/Twig names Base\Twig\); a function no extension
 * registered answers as it did when its extension was missing.
 */
class AccountScreens
{
    /** The width and height of a profile's avatar. */
    public const AVATAR = 128;

    public function __construct(
        private readonly Accounts $accounts,
        private readonly Environment $twig,
        private readonly ?Access $access = null,
    ) {
    }

    /** A member's profile, as the visitor asking sees it. */
    public function profile(User $user): ProfilePage
    {
        $viewer = $this->access?->getUser();
        $own = $viewer instanceof User && null !== $viewer->getId() && $viewer->getId() === $user->getId();

        return $this->accounts->profile($user, $own, (bool) $this->access?->isGranted('ROLE_ADMIN'), $this->avatar($user));
    }

    /** @param iterable<\Base\Entity\User\Connection> $sessions */
    public function settings(?User $user, iterable $sessions = []): SettingsPage
    {
        $oauth = $this->function('oauth_available');
        $available = [];
        foreach (array_keys(Accounts::PROVIDERS) as $name) {
            $available[$name] = null !== $oauth && (bool) $oauth($name);
        }

        return $this->accounts->settings($user, $sessions, $available);
    }

    /** @param iterable<Notification> $notifications */
    public function notifications(iterable $notifications, int $unread, bool $unreadOnly = false): NotificationsPage
    {
        $available = $this->function('push_available');
        $key = $this->function('push_vapid_key');
        $adminUrl = $this->function('notification_admin_url');
        $push = null !== $available && (bool) $available();

        return $this->accounts->notifications(
            $notifications,
            $unread,
            $unreadOnly,
            $push,
            $push && null !== $key ? $key() : null,
            null !== $adminUrl ? static fn (Notification $notification): ?string => $adminUrl($notification) : null,
            $this->twig->getExtension(CoreExtension::class)->getTimezone(),
        );
    }

    /** The stored avatar's thumbnail, as `|thumbnail(128, 128)` gave it; null for none. */
    private function avatar(User $user): ?string
    {
        if (!$user->getAvatar()) {
            return null;
        }
        $filter = $this->twig->getFilter('thumbnail');
        $thumbnail = null !== $filter ? ($filter->getCallable())([], $user->getAvatar(), self::AVATAR, self::AVATAR) : null;

        return \is_string($thumbnail) ? $thumbnail : '';
    }

    /** A Twig function by its name, as a template calls it; null when no extension registered it. */
    private function function(string $name): ?callable
    {
        $function = $this->twig->getFunction($name);

        return null !== $function ? $function->getCallable() : null;
    }
}
