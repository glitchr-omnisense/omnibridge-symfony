<?php

namespace Omnibridge\Symfony\Omnibase\Form;

/**
 * The Symfony form type of each neutral kind (Base\Field\Kind): what the
 * field descriptors named themselves before they named a kind, so a
 * descriptor's getFormType() answers the same class as before.
 *
 * Plain strings: nothing here loads Symfony Form. Base\Field\Kinds reads it
 * by name when Symfony is the host.
 */
final class SymfonyKinds
{
    public const MAP = [
        'text' => 'Symfony\Component\Form\Extension\Core\Type\TextType',
        'textarea' => 'Symfony\Component\Form\Extension\Core\Type\TextareaType',
        'email' => 'Symfony\Component\Form\Extension\Core\Type\EmailType',
        'password' => 'Base\Field\Type\PasswordType',
        'url' => 'Symfony\Component\Form\Extension\Core\Type\UrlType',
        'tel' => 'Symfony\Component\Form\Extension\Core\Type\TelType',
        'search' => 'Symfony\Component\Form\Extension\Core\Type\SearchType',
        'slug' => 'Base\Field\Type\SlugType',
        'code' => 'Base\Field\Type\CodeType',
        'editor' => 'Base\Field\Type\EditorType',
        'wysiwyg' => 'Base\Field\Type\WysiwygType',
        'hidden' => 'Symfony\Component\Form\Extension\Core\Type\HiddenType',

        'integer' => 'Symfony\Component\Form\Extension\Core\Type\IntegerType',
        'number' => 'Base\Field\Type\NumberType',
        'money' => 'Base\Field\Type\MoneyType',
        'forex' => 'Base\Field\Type\ForexType',
        'stock' => 'Base\Field\Type\StockType',
        'year' => 'Symfony\Component\Form\Extension\Core\Type\IntegerType',

        'boolean' => 'Base\Field\Type\BooleanType',
        'checkbox' => 'Symfony\Component\Form\Extension\Core\Type\CheckboxType',
        'choice' => 'Base\Field\Type\SelectType',
        'country' => 'Base\Field\Type\CountryType',
        'currency' => 'Base\Field\Type\CurrencyType',
        'locale' => 'Symfony\Component\Form\Extension\Core\Type\LocaleType',
        'gender' => 'Base\Field\Type\GenderType',
        'role' => 'Base\Field\Type\RoleType',
        'state' => 'Base\Field\Type\StateType',
        'route' => 'Base\Field\Type\RouteType',
        'icon' => 'Base\Field\Type\IconType',
        'emoji' => 'Base\Field\Type\EmojiPickerType',
        'color' => 'Base\Field\Type\ColorType',
        'color_picker' => 'Base\Field\Type\ColorPickerType',
        'quadrant' => 'Base\Field\Type\QuadrantType',

        'date' => 'Symfony\Component\Form\Extension\Core\Type\DateType',
        'datetime' => 'Symfony\Component\Form\Extension\Core\Type\DateTimeType',
        'datetime_picker' => 'Base\Field\Type\DateTimePickerType',
        'time' => 'Symfony\Component\Form\Extension\Core\Type\TimeType',

        'file' => 'Base\Field\Type\FileType',
        'image' => 'Base\Field\Type\ImageType',
        'cropper' => 'Base\Field\Type\ImageType',
        'avatar' => 'Base\Field\Type\AvatarType',
        'audio' => 'Base\Field\Type\AudioType',
        'video' => 'Base\Field\Type\VideoType',

        'association' => 'Base\Field\Type\AssociationType',
        'association_file' => 'Base\Field\Type\AssociationFileType',
        'attribute' => 'Base\Field\Type\AttributeType',
        'array' => 'Base\Field\Type\ArrayType',
        'collection' => 'Base\Field\Type\CollectionType',
        'repeated' => 'Symfony\Component\Form\Extension\Core\Type\RepeatedType',
        'translation' => 'Base\Field\Type\TranslationType',
        'discriminator' => 'Symfony\Component\Form\Extension\Core\Type\HiddenType',

        'submit' => 'Base\Field\Type\SubmitType',
        'button' => 'Base\Field\Type\ButtonType',
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
        'Base\Field\Type\DiscriminatorType' => 'discriminator',
        'Base\Field\Type\CropperType' => 'cropper',
        'Base\Field\Type\TranslatableType' => 'translation',
    ];

    private function __construct()
    {
    }
}
