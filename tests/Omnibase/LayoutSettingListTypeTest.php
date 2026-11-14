<?php

namespace Omnibridge\Symfony\Tests\Omnibase;

use Omnibridge\Symfony\Omnibase\Field\Type\DateTimePickerType;
use Omnibridge\Symfony\Omnibase\Field\Type\FileType;
use Omnibridge\Symfony\Omnibase\Field\Type\ImageType;
use Omnibridge\Symfony\Omnibase\Form\FormTypes;
use Omnibridge\Symfony\Omnibase\Form\Type\LayoutSettingListType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

/**
 * The settings form (omnibase/admin's /admin/settings): a setting declared with ImageType or FileType gets the upload
 * options, one with DateTimePickerType the date conversion, and a plain Save submits the stored data (1.x proved the
 * same under the former names, Base\Field\Type\X, which omnisense/omnibase 6.0 no longer declares). And a date setting the bag already holds as a date
 * (a form built before in the same request) stays that date.
 *
 * Needs the core and a host kernel: run in the omnibase harness, where omnibridge/symfony is the
 * checkout (compose.checkouts.yml); skipped by this package's own suite, which installs no core.
 */
class LayoutSettingListTypeTest extends KernelTestCase
{
    private const IMAGE = 'app.settings.layout_setting_list_test.image';
    private const DATE = 'app.settings.layout_setting_list_test.date';
    private const STORED_DATE = '2026-11-14 10:30:00';

    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel') || !class_exists('Base\\Service\\SettingBag')) {
            self::markTestSkipped('Requires omnisense/omnibase and a host application kernel (the omnibase harness).');
        }

        self::bootKernel();
    }

    /** @return iterable<string, array{string, string}> */
    public static function names(): iterable
    {
        yield 'image' => ['\\Omnibridge\\Symfony\\Omnibase\\Field\\Type\\ImageType', ImageType::class];
        yield 'file' => ['\\Omnibridge\\Symfony\\Omnibase\\Field\\Type\\FileType', FileType::class];
        yield 'date time picker' => ['\\Omnibridge\\Symfony\\Omnibase\\Field\\Type\\DateTimePickerType', DateTimePickerType::class];
    }

    /** @dataProvider names */
    #[\PHPUnit\Framework\Attributes\DataProvider('names')]
    public function testANameWithOrWithoutItsLeadingBackslash(string $former, string $new): void
    {
        $this->assertTrue(FormTypes::same($former, $new));
        $this->assertTrue(FormTypes::same($new, $former));
        $this->assertTrue(FormTypes::same('\\'.$former, $new));
        $this->assertTrue(FormTypes::in($former, [TextType::class, $new]));
        $this->assertFalse(FormTypes::same($former, TextType::class));
        $this->assertFalse(FormTypes::same(null, $new));
        $this->assertSame($new, FormTypes::declared($former), 'without its leading backslash');
        $this->assertSame(TextType::class, FormTypes::declared(TextType::class));
    }

    public function testAnUploadSettingGetsItsOptions(): void
    {
        foreach ([ImageType::class, FileType::class] as $type) {
            $this->store();
            $value = $this->value($this->form($type, DateTimePickerType::class), self::IMAGE);
            $this->assertSame($type, \get_class($value->getConfig()->getType()->getInnerType()));
            $options = array_intersect_key($value->getConfig()->getOptions(), array_flip(['max_size', 'mime_types', 'empty_data']));

            $this->assertNotNull($options['max_size'], $type.' gets the upload options');
            $this->assertSame('logo.png', $options['empty_data'], 'its own stored value, what a plain Save binds');
        }
    }

    public function testADateSettingIsConverted(): void
    {
        $this->store();
        $date = $this->value($this->form(ImageType::class, DateTimePickerType::class), self::DATE)->getData();

        $this->assertInstanceOf(\DateTimeInterface::class, $date);
        $this->assertSame(self::STORED_DATE, $date->format('Y-m-d H:i:s'));
    }

    public function testAPlainSaveSubmitsTheStoredData(): void
    {
        $this->store();
        $form = $this->form(ImageType::class, DateTimePickerType::class);
        $form->submit($this->displayed($form->createView()));
        $this->assertTrue($form->isSubmitted());

        $data = [];
        foreach ($form->getData() as $path => $setting) {
            $value = $setting->translate($this->locale())->getValue();
            $data[$path] = $value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i:s') : $value;
        }
        ksort($data);

        $this->assertSame([self::DATE, self::IMAGE], array_keys($data));
        $this->assertSame(self::STORED_DATE, $data[self::DATE]);
    }

    public function testADateTheBagAlreadyHoldsAsADateIsKept(): void
    {
        foreach ([DateTimePickerType::class] as $type) {
            $this->store();
            // The first form converts the stored string on the SettingIntl the bag holds; the second
            // reads that same SettingIntl, a DateTime now (two forms of one request, a page and its
            // quick settings).
            $first = $this->value($this->form(ImageType::class, $type), self::DATE)->getData();
            $second = $this->value($this->form(ImageType::class, $type), self::DATE)->getData();

            $this->assertInstanceOf(\DateTimeInterface::class, $first, $type);
            $this->assertInstanceOf(\DateTimeInterface::class, $second, $type.': a DateTime is kept, not set to null');
            $this->assertSame(self::STORED_DATE, $second->format('Y-m-d H:i:s'), $type);
        }
    }

    /** The two settings stored, read by a fresh kernel (a form converts the SettingIntl it is given). */
    private function store(): void
    {
        self::ensureKernelShutdown();
        self::bootKernel();
        $bag = static::getContainer()->get('Base\\Service\\SettingBag');
        $bag->set(self::IMAGE, 'logo.png');
        $bag->set(self::DATE, self::STORED_DATE);
    }

    private function locale(): string
    {
        return static::getContainer()->get('Base\\Service\\Localizer')->getDefaultLocale();
    }

    private function form(string $image, string $date): FormInterface
    {
        return static::getContainer()->get('form.factory')->create(LayoutSettingListType::class, null, [
            'csrf_protection' => false,
            'fields' => [
                self::IMAGE => ['form_type' => $image],
                self::DATE => ['form_type' => $date],
            ],
        ]);
    }

    /** The field holding a setting's value (unv > locale > _collection > setting > value). */
    private function value(FormInterface $form, string $path): FormInterface
    {
        return $form->get('unv')->get($this->locale())->get('_collection')->get(str_replace('.', '-', $path))->get('value');
    }

    /**
     * What the browser sends back for a page drawn from $view and saved untouched.
     *
     * @return array<string, mixed>|string|null
     */
    private function displayed(FormView $view): array|string|null
    {
        if (!$view->vars['compound']) {
            if (\in_array('checkbox', $view->vars['block_prefixes'], true)) {
                return $view->vars['checked'] ? (string) $view->vars['value'] : null;
            }

            return \is_scalar($view->vars['value']) ? (string) $view->vars['value'] : null;
        }

        $data = [];
        foreach ($view->children as $name => $child) {
            if (\in_array('submit', $child->vars['block_prefixes'], true)) {
                continue;
            }
            $value = $this->displayed($child);
            if (null !== $value) {
                $data[$name] = $value;
            }
        }

        return $data;
    }
}
