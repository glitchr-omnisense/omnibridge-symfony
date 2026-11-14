<?php

namespace Omnibridge\Symfony\Omnibase\Form;

/**
 * The Symfony form type of each neutral kind (Base\Field\Kind): what the
 * field descriptors named themselves before they named a kind, so a
 * descriptor's getFormType() answers the same class as before.
 *
 * Plain strings: nothing here loads Symfony Form. Base\Field\Kinds reads it
 * by name when Symfony is the host.
 *
 * COVERAGE is what Symfony draws (plan "omnibase agnostic", step F5d): every
 * kind of MAP through its type, a custom one through the type it names; the
 * markup given whole (Kind::HTML, a captcha's widget on a host without form
 * types) is no Symfony type, DefinitionForms refuses it aloud.
 */
final class SymfonyKinds
{
    public const MAP = [
        'text' => 'Symfony\Component\Form\Extension\Core\Type\TextType',
        'textarea' => 'Symfony\Component\Form\Extension\Core\Type\TextareaType',
        'email' => 'Symfony\Component\Form\Extension\Core\Type\EmailType',
        'password' => 'Omnibridge\Symfony\Omnibase\Field\Type\PasswordType',
        'url' => 'Symfony\Component\Form\Extension\Core\Type\UrlType',
        'tel' => 'Symfony\Component\Form\Extension\Core\Type\TelType',
        'search' => 'Symfony\Component\Form\Extension\Core\Type\SearchType',
        'slug' => 'Omnibridge\Symfony\Omnibase\Field\Type\SlugType',
        'code' => 'Omnibridge\Symfony\Omnibase\Field\Type\CodeType',
        'editor' => 'Omnibridge\Symfony\Omnibase\Field\Type\EditorType',
        'wysiwyg' => 'Omnibridge\Symfony\Omnibase\Field\Type\WysiwygType',
        'hidden' => 'Symfony\Component\Form\Extension\Core\Type\HiddenType',

        'integer' => 'Symfony\Component\Form\Extension\Core\Type\IntegerType',
        'number' => 'Omnibridge\Symfony\Omnibase\Field\Type\NumberType',
        'money' => 'Omnibridge\Symfony\Omnibase\Field\Type\MoneyType',
        'forex' => 'Omnibridge\Symfony\Omnibase\Field\Type\ForexType',
        'stock' => 'Omnibridge\Symfony\Omnibase\Field\Type\StockType',
        'year' => 'Symfony\Component\Form\Extension\Core\Type\IntegerType',

        'boolean' => 'Omnibridge\Symfony\Omnibase\Field\Type\BooleanType',
        'checkbox' => 'Symfony\Component\Form\Extension\Core\Type\CheckboxType',
        'choice' => 'Omnibridge\Symfony\Omnibase\Field\Type\SelectType',
        'country' => 'Omnibridge\Symfony\Omnibase\Field\Type\CountryType',
        'currency' => 'Omnibridge\Symfony\Omnibase\Field\Type\CurrencyType',
        'locale' => 'Symfony\Component\Form\Extension\Core\Type\LocaleType',
        'gender' => 'Omnibridge\Symfony\Omnibase\Field\Type\GenderType',
        'role' => 'Omnibridge\Symfony\Omnibase\Field\Type\RoleType',
        'state' => 'Omnibridge\Symfony\Omnibase\Field\Type\StateType',
        'route' => 'Omnibridge\Symfony\Omnibase\Field\Type\RouteType',
        'icon' => 'Omnibridge\Symfony\Omnibase\Field\Type\IconType',
        'emoji' => 'Omnibridge\Symfony\Omnibase\Field\Type\EmojiPickerType',
        'color' => 'Omnibridge\Symfony\Omnibase\Field\Type\ColorType',
        'color_picker' => 'Omnibridge\Symfony\Omnibase\Field\Type\ColorPickerType',
        'quadrant' => 'Omnibridge\Symfony\Omnibase\Field\Type\QuadrantType',

        'date' => 'Symfony\Component\Form\Extension\Core\Type\DateType',
        'datetime' => 'Symfony\Component\Form\Extension\Core\Type\DateTimeType',
        'datetime_picker' => 'Omnibridge\Symfony\Omnibase\Field\Type\DateTimePickerType',
        'time' => 'Symfony\Component\Form\Extension\Core\Type\TimeType',

        'file' => 'Omnibridge\Symfony\Omnibase\Field\Type\FileType',
        'image' => 'Omnibridge\Symfony\Omnibase\Field\Type\ImageType',
        'cropper' => 'Omnibridge\Symfony\Omnibase\Field\Type\ImageType',
        'avatar' => 'Omnibridge\Symfony\Omnibase\Field\Type\AvatarType',
        'audio' => 'Omnibridge\Symfony\Omnibase\Field\Type\AudioType',
        'video' => 'Omnibridge\Symfony\Omnibase\Field\Type\VideoType',

        'association' => 'Omnibridge\Symfony\Omnibase\Field\Type\AssociationType',
        'association_file' => 'Omnibridge\Symfony\Omnibase\Field\Type\AssociationFileType',
        'attribute' => 'Omnibridge\Symfony\Omnibase\Field\Type\AttributeType',
        'array' => 'Omnibridge\Symfony\Omnibase\Field\Type\ArrayType',
        'collection' => 'Omnibridge\Symfony\Omnibase\Field\Type\CollectionType',
        'repeated' => 'Symfony\Component\Form\Extension\Core\Type\RepeatedType',
        'translation' => 'Omnibridge\Symfony\Omnibase\Field\Type\TranslationType',
        'discriminator' => 'Symfony\Component\Form\Extension\Core\Type\HiddenType',

        'submit' => 'Omnibridge\Symfony\Omnibase\Field\Type\SubmitType',
        'button' => 'Omnibridge\Symfony\Omnibase\Field\Type\ButtonType',
    ];

    public const COVERAGE = [
        'text' => 'implemented', 'textarea' => 'implemented', 'email' => 'implemented', 'password' => 'implemented',
        'url' => 'implemented', 'tel' => 'implemented', 'search' => 'implemented', 'slug' => 'implemented',
        'code' => 'implemented', 'editor' => 'implemented', 'wysiwyg' => 'implemented', 'hidden' => 'implemented',
        'integer' => 'implemented', 'number' => 'implemented', 'money' => 'implemented', 'forex' => 'implemented',
        'stock' => 'implemented', 'year' => 'implemented',
        'boolean' => 'implemented', 'checkbox' => 'implemented', 'choice' => 'implemented', 'country' => 'implemented',
        'currency' => 'implemented', 'locale' => 'implemented', 'gender' => 'implemented', 'role' => 'implemented',
        'state' => 'implemented', 'route' => 'implemented', 'icon' => 'implemented', 'emoji' => 'implemented',
        'color' => 'implemented', 'color_picker' => 'implemented', 'quadrant' => 'implemented',
        'date' => 'implemented', 'datetime' => 'implemented', 'datetime_picker' => 'implemented', 'time' => 'implemented',
        'file' => 'implemented', 'image' => 'implemented', 'cropper' => 'implemented', 'avatar' => 'implemented',
        'audio' => 'implemented', 'video' => 'implemented',
        'association' => 'implemented', 'association_file' => 'implemented', 'attribute' => 'implemented',
        'array' => 'implemented', 'collection' => 'implemented', 'repeated' => 'implemented', 'translation' => 'implemented',
        'discriminator' => 'implemented',
        'submit' => 'implemented', 'button' => 'implemented',
        'html' => 'unsupported: markup given whole is a renderer\'s (Laravel\'s captcha row); a Symfony form adds a type, a template prints markup',
        'custom' => 'implemented',
    ];

    /** Symfony types no kind names first: the kind they render. */
    public const ALIASES = [
        'Symfony\Component\Form\Extension\Core\Type\ChoiceType' => 'choice',
        'Symfony\Component\Form\Extension\Core\Type\EnumType' => 'choice',
        'Symfony\Component\Form\Extension\Core\Type\CountryType' => 'country',
        'Symfony\Component\Form\Extension\Core\Type\CurrencyType' => 'currency',
        'Symfony\Component\Form\Extension\Core\Type\NumberType' => 'number',
        'Symfony\Component\Form\Extension\Core\Type\MoneyType' => 'money',
        'Symfony\Component\Form\Extension\Core\Type\PasswordType' => 'password',
        'Symfony\Component\Form\Extension\Core\Type\FileType' => 'file',
        'Symfony\Component\Form\Extension\Core\Type\ColorType' => 'color',
        'Symfony\Component\Form\Extension\Core\Type\CollectionType' => 'collection',
        'Symfony\Component\Form\Extension\Core\Type\SubmitType' => 'submit',
        'Symfony\Component\Form\Extension\Core\Type\ButtonType' => 'button',
        'Symfony\Component\Form\Extension\Core\Type\BirthdayType' => 'date',
        'Omnibridge\Symfony\Omnibase\Field\Type\DiscriminatorType' => 'discriminator',
        'Omnibridge\Symfony\Omnibase\Field\Type\CropperType' => 'cropper',
        'Omnibridge\Symfony\Omnibase\Field\Type\TranslatableType' => 'translation',
    ];

    private function __construct()
    {
    }
}
