<?php

declare(strict_types=1);

/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace yiiunit\framework\helpers;

use yii\helpers\Inflector;
use yiiunit\TestCase;

/**
 * @group helpers
 */
class InflectorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // destroy application, Helper must work without Yii::$app
        $this->destroyApplication();
    }

    public function testPluralize(): void
    {
        $testData = [
            'move' => 'moves',
            'foot' => 'feet',
            'child' => 'children',
            'human' => 'humans',
            'man' => 'men',
            'staff' => 'staff',
            'tooth' => 'teeth',
            'person' => 'people',
            'mouse' => 'mice',
            'touch' => 'touches',
            'hash' => 'hashes',
            'shelf' => 'shelves',
            'potato' => 'potatoes',
            'bus' => 'buses',
            'test' => 'tests',
            'car' => 'cars',
            'netherlands' => 'netherlands',
            'currency' => 'currencies',
            'software' => 'software',
            'hardware' => 'hardware',
        ];

        foreach ($testData as $testIn => $testOut) {
            $this->assertEquals($testOut, Inflector::pluralize($testIn));
            $this->assertEquals(ucfirst($testOut), ucfirst(Inflector::pluralize($testIn)));
        }
    }

    public function testSingularize(): void
    {
        $testData = [
            'moves' => 'move',
            'feet' => 'foot',
            'children' => 'child',
            'humans' => 'human',
            'men' => 'man',
            'staff' => 'staff',
            'teeth' => 'tooth',
            'people' => 'person',
            'mice' => 'mouse',
            'touches' => 'touch',
            'hashes' => 'hash',
            'shelves' => 'shelf',
            'potatoes' => 'potato',
            'buses' => 'bus',
            'tests' => 'test',
            'cars' => 'car',
            'Netherlands' => 'Netherlands',
            'currencies' => 'currency',
            'software' => 'software',
            'hardware' => 'hardware',
        ];
        foreach ($testData as $testIn => $testOut) {
            $this->assertEquals($testOut, Inflector::singularize($testIn));
            $this->assertEquals(ucfirst($testOut), ucfirst(Inflector::singularize($testIn)));
        }
    }

    public function testTitleize(): void
    {
        $this->assertEquals('Me my self and i', Inflector::titleize('MeMySelfAndI'));
        $this->assertEquals('Me My Self And I', Inflector::titleize('MeMySelfAndI', true));
        $this->assertEquals('Треба Більше Тестів!', Inflector::titleize('ТребаБільшеТестів!', true));
    }

    public function testCamelize(): void
    {
        $this->assertEquals('MeMySelfAndI', Inflector::camelize('me my_self-andI'));
        $this->assertEquals('QweQweEwq', Inflector::camelize('qwe qwe^ewq'));
        $this->assertEquals('ВідомоЩоТестиЗберігатьНашіНЕРВИ', Inflector::camelize('Відомо, що тести зберігать наші НЕРВИ! 🙃'));
    }

    public function testUnderscore(): void
    {
        $this->assertEquals('me_my_self_and_i', Inflector::underscore('MeMySelfAndI'));
        $this->assertEquals('кожний_тест_особливий', Inflector::underscore('КожнийТестОсобливий'));
    }

    public function testCamel2words(): void
    {
        $this->assertEquals('Camel Case', Inflector::camel2words('camelCase'));
        $this->assertEquals('Camel Case', Inflector::camel2words('CamelCase'));
        $this->assertEquals('Lower Case', Inflector::camel2words('lower_case'));
        $this->assertEquals('Tricky Stuff It Is Testing', Inflector::camel2words(' tricky_stuff.it-is testing... '));
        $this->assertEquals('І Це Дійсно Так!', Inflector::camel2words('ІЦеДійсноТак!'));
        $this->assertEquals('Test', Inflector::camel2words('TEST'));
        $this->assertEquals('X Foo', Inflector::camel2words('XFoo'));
        $this->assertEquals('Foo Bar Baz', Inflector::camel2words('FooBARBaz'));
        $this->assertEquals('Generate Csrf', Inflector::camel2words('generateCSRF'));
        $this->assertEquals('Generate Csrf Token', Inflector::camel2words('generateCSRFToken'));
        $this->assertEquals('Csrf Token Generator', Inflector::camel2words('CSRFTokenGenerator'));
        $this->assertEquals('Foo Bar', Inflector::camel2words('foo bar'));
        $this->assertEquals('Foo Bar', Inflector::camel2words('foo BAR'));
        $this->assertEquals('Foo Bar', Inflector::camel2words('Foo Bar'));
        $this->assertEquals('Foo Bar', Inflector::camel2words('FOO BAR'));
    }

    public function testCamel2id(): void
    {
        $this->assertEquals('post-tag', Inflector::camel2id('PostTag'));
        $this->assertEquals('post_tag', Inflector::camel2id('PostTag', '_'));
        $this->assertEquals('єдиний_код', Inflector::camel2id('ЄдинийКод', '_'));

        $this->assertEquals('post-tag', Inflector::camel2id('postTag'));
        $this->assertEquals('post_tag', Inflector::camel2id('postTag', '_'));
        $this->assertEquals('єдиний_код', Inflector::camel2id('єдинийКод', '_'));

        $this->assertEquals('foo-ybar', Inflector::camel2id('FooYBar', '-', false));
        $this->assertEquals('foo_ybar', Inflector::camel2id('fooYBar', '_', false));
        $this->assertEquals('невже_іце_працює', Inflector::camel2id('НевжеІЦеПрацює', '_', false));

        $this->assertEquals('foo-y-bar', Inflector::camel2id('FooYBar', '-', true));
        $this->assertEquals('foo_y_bar', Inflector::camel2id('fooYBar', '_', true));
        $this->assertEquals('foo_y_bar', Inflector::camel2id('fooYBar', '_', true));
        $this->assertEquals('невже_і_це_працює', Inflector::camel2id('НевжеІЦеПрацює', '_', true));
    }

    public function testId2camel(): void
    {
        $this->assertEquals('PostTag', Inflector::id2camel('post-tag'));
        $this->assertEquals('PostTag', Inflector::id2camel('post_tag', '_'));
        $this->assertEquals('ЄдинийСвіт', Inflector::id2camel('єдиний_світ', '_'));

        $this->assertEquals('PostTag', Inflector::id2camel('post-tag'));
        $this->assertEquals('PostTag', Inflector::id2camel('post_tag', '_'));
        $this->assertEquals('НевжеІЦеПрацює', Inflector::id2camel('невже_і_це_працює', '_'));

        $this->assertEquals('ShouldNotBecomeLowercased', Inflector::id2camel('ShouldNotBecomeLowercased', '_'));

        $this->assertEquals('FooYBar', Inflector::id2camel('foo-y-bar'));
        $this->assertEquals('FooYBar', Inflector::id2camel('foo_y_bar', '_'));
    }

    public function testHumanize(): void
    {
        $this->assertEquals('Me my self and i', Inflector::humanize('me_my_self_and_i'));
        $this->assertEquals('Me My Self And I', Inflector::humanize('me_my_self_and_i', true));
        $this->assertEquals('Але й веселі ці ваші тести', Inflector::humanize('але_й_веселі_ці_ваші_тести'));
    }

    public function testVariablize(): void
    {
        $this->assertEquals('customerTable', Inflector::variablize('customer_table'));
        $this->assertEquals('ひらがなHepimiz', Inflector::variablize('ひらがな_hepimiz'));
    }

    public function testTableize(): void
    {
        $this->assertEquals('customer_tables', Inflector::tableize('customerTable'));
    }

    public function testSlugCommons(): void
    {
        $data = [
            '' => '',
            'hello world 123' => 'hello-world-123',
            'remove.!?[]{}…symbols' => 'removesymbols',
            'minus-sign' => 'minus-sign',
            'mdash—sign' => 'mdash-sign',
            'ndash–sign' => 'ndash-sign',
            'áàâéèêíìîóòôúùûã' => 'aaaeeeiiiooouuua',
            'älä lyö ääliö ööliä läikkyy' => 'ala-lyo-aalio-oolia-laikkyy',
        ];

        foreach ($data as $source => $expected) {
            if (extension_loaded('intl')) {
                $this->assertEquals($expected, FallbackInflector::slug($source));
            }
            $this->assertEquals($expected, Inflector::slug($source));
        }
    }

    public function testSlugReplacements(): void
    {
        $this->assertEquals('dont_replace_replacement', Inflector::slug('dont replace_replacement', '_'));
        $this->assertEquals('remove_trailing_replacements', Inflector::slug('_remove trailing replacements_', '_'));
        $this->assertEquals('remove_excess_replacements', Inflector::slug(' _ _ remove excess _ _ replacements_', '_'));
        $this->assertEquals('thisrepisreprepreplacement', Inflector::slug('this is REP-lacement', 'REP'));
        $this->assertEquals('0_100_kmh', Inflector::slug('0-100 Km/h', '_'));
        $this->assertEquals('testtext', Inflector::slug('test text', ''));
    }

    public function testSlugIntl(): void
    {
        if (!extension_loaded('intl')) {
            $this->markTestSkipped('intl extension is required.');
        }

        // Some test strings are from https://github.com/bergie/midgardmvc_helper_urlize. Thank you, Henri Bergius!
        $data = [
            // Korean
            '해동검도' => 'haedong-geomdo',
            // Hiragana
            'ひらがな' => 'hiragana',
            // Georgian
            'საქართველო' => 'sakartvelo',
            // Arabic
            'العربي' => 'alrby',
            'عرب' => 'rb',
            // Hebrew
            'עִבְרִית' => 'iberiyt',
            // Turkish
            'Sanırım hepimiz aynı şeyi düşünüyoruz.' => 'sanirim-hepimiz-ayni-seyi-dusunuyoruz',
            // Russian
            'недвижимость' => 'nedvizimost',
            'Контакты' => 'kontakty',
            // Chinese
            '美国' => 'mei-guo',
            // Estonian
            'Jääär' => 'jaaar',
        ];

        foreach ($data as $source => $expected) {
            $this->assertEquals($expected, Inflector::slug($source));
        }
    }

    public function testTransliterateStrict(): void
    {
        if (!extension_loaded('intl')) {
            $this->markTestSkipped('intl extension is required.');
        }

        // Some test strings are from https://github.com/bergie/midgardmvc_helper_urlize. Thank you, Henri Bergius!
        $data = [
            // Korean
            '해동검도' => 'haedong-geomdo',
            // Hiragana
            'ひらがな' => 'hiragana',
            // Georgian
            'საქართველო' => 'sakartvelo',
            // Arabic
            'العربي' => 'ạlʿrby',
            'عرب' => 'ʿrb',
            // Hebrew
            'עִבְרִית' => 'ʻibĕriyţ',
            // Turkish
            'Sanırım hepimiz aynı şeyi düşünüyoruz.' => 'Sanırım hepimiz aynı şeyi düşünüyoruz.',

            // Russian
            'недвижимость' => 'nedvižimostʹ',
            'Контакты' => 'Kontakty',

            // Ukrainian
            'Українська: ґанок, європа' => 'Ukraí̈nsʹka: g̀anok, êvropa',

            // Serbian
            'Српска: ђ, њ, џ!' => 'Srpska: đ, n̂, d̂!',

            // Spanish
            '¿Español?' => '¿Español?',
            // Chinese
            '美国' => 'měi guó',
        ];

        foreach ($data as $source => $expected) {
            $this->assertEquals($expected, Inflector::transliterate($source, Inflector::TRANSLITERATE_STRICT));
        }
    }

    public function testTransliterateMedium(): void
    {
        if (!extension_loaded('intl')) {
            $this->markTestSkipped('intl extension is required.');
        }

        // Some test strings are from https://github.com/bergie/midgardmvc_helper_urlize. Thank you, Henri Bergius!
        $data = [
            // Korean
            '해동검도' => ['haedong-geomdo'],
            // Hiragana
            'ひらがな' => ['hiragana'],
            // Georgian
            'საქართველო' => ['sakartvelo'],
            // Arabic
            'العربي' => ['alʿrby'],
            'عرب' => ['ʿrb'],
            // Hebrew
            'עִבְרִית' => ['\'iberiyt', 'ʻiberiyt'],
            // Turkish
            'Sanırım hepimiz aynı şeyi düşünüyoruz.' => ['Sanirim hepimiz ayni seyi dusunuyoruz.'],

            // Russian
            'недвижимость' => ['nedvizimost\'', 'nedvizimostʹ'],
            'Контакты' => ['Kontakty'],

            // Ukrainian
            'Українська: ґанок, європа' => ['Ukrainsʹka: ganok, evropa', 'Ukrains\'ka: ganok, evropa'],

            // Serbian
            'Српска: ђ, њ, џ!' => ['Srpska: d, n, d!'],

            // Spanish
            '¿Español?' => ['¿Espanol?', '?Espanol?'],
            // Chinese
            '美国' => ['mei guo'],
        ];

        foreach ($data as $source => $allowed) {
            $this->assertIsOneOf(Inflector::transliterate($source, Inflector::TRANSLITERATE_MEDIUM), $allowed);
        }
    }

    public function testTransliterateLoose(): void
    {
        if (!extension_loaded('intl')) {
            $this->markTestSkipped('intl extension is required.');
        }

        // Some test strings are from https://github.com/bergie/midgardmvc_helper_urlize. Thank you, Henri Bergius!
        $data = [
            // Korean
            '해동검도' => ['haedong-geomdo'],
            // Hiragana
            'ひらがな' => ['hiragana'],
            // Georgian
            'საქართველო' => ['sakartvelo'],
            // Arabic
            'العربي' => ['alrby'],
            'عرب' => ['rb'],
            // Hebrew
            'עִבְרִית' => ['\'iberiyt', 'iberiyt'],
            // Turkish
            'Sanırım hepimiz aynı şeyi düşünüyoruz.' => ['Sanirim hepimiz ayni seyi dusunuyoruz.'],

            // Russian
            'недвижимость' => ['nedvizimost\'', 'nedvizimost'],
            'Контакты' => ['Kontakty'],

            // Ukrainian
            'Українська: ґанок, європа' => ['Ukrainska: ganok, evropa', 'Ukrains\'ka: ganok, evropa'],

            // Serbian
            'Српска: ђ, њ, џ!' => ['Srpska: d, n, d!'],

            // Spanish
            '¿Español?' => ['Espanol?', '?Espanol?'],
            // Chinese
            '美国' => ['mei guo'],
        ];

        foreach ($data as $source => $allowed) {
            $this->assertIsOneOf(Inflector::transliterate($source, Inflector::TRANSLITERATE_LOOSE), $allowed);
        }
    }

    public function testSlugPhp(): void
    {
        $data = [
            'we have недвижимость' => 'we-have',
        ];

        foreach ($data as $source => $expected) {
            $this->assertEquals($expected, FallbackInflector::slug($source));
        }
    }

    public function testClassify(): void
    {
        $this->assertEquals('CustomerTable', Inflector::classify('customer_tables'));
    }

    public function testOrdinalize(): void
    {
        $this->assertEquals('21st', Inflector::ordinalize('21'));
        $this->assertEquals('22nd', Inflector::ordinalize('22'));
        $this->assertEquals('23rd', Inflector::ordinalize('23'));
        $this->assertEquals('24th', Inflector::ordinalize('24'));
        $this->assertEquals('25th', Inflector::ordinalize('25'));
        $this->assertEquals('111th', Inflector::ordinalize('111'));
        $this->assertEquals('113th', Inflector::ordinalize('113'));
    }

    public function testSentence(): void
    {
        $array = [];
        $this->assertEquals('', Inflector::sentence($array));

        $array = ['Spain'];
        $this->assertEquals('Spain', Inflector::sentence($array));

        $array = ['Spain', 'France'];
        $this->assertEquals('Spain and France', Inflector::sentence($array));

        $array = ['Spain', 'France', 'Italy'];
        $this->assertEquals('Spain, France and Italy', Inflector::sentence($array));

        $array = ['Spain', 'France', 'Italy', 'Germany'];
        $this->assertEquals('Spain, France, Italy and Germany', Inflector::sentence($array));

        $array = ['Spain', 'France'];
        $this->assertEquals('Spain or France', Inflector::sentence($array, ' or '));

        $array = ['Spain', 'France', 'Italy'];
        $this->assertEquals('Spain, France or Italy', Inflector::sentence($array, ' or '));

        $array = ['Spain', 'France'];
        $this->assertEquals('Spain and France', Inflector::sentence($array, ' and ', ' or ', ' - '));

        $array = ['Spain', 'France', 'Italy'];
        $this->assertEquals('Spain - France or Italy', Inflector::sentence($array, ' and ', ' or ', ' - '));
    }
}
