<?php

/* Copyright (c) 2018 Nils Haagen <nils.haagen@concepts-and-training.de> Extended GPL, see docs/LICENSE */

require_once(__DIR__ . "/../../../../../libs/composer/vendor/autoload.php");
require_once(__DIR__ . "/../../../Base.php");

use ILIAS\UI\Implementation\Component\SignalGenerator;
use ILIAS\UI\Implementation\Component\Input\NameSource;
use ILIAS\UI\Component\Input\Field;
use ILIAS\Data;
use ILIAS\Refinery\Validation;
use ILIAS\Refinery\Transformation;
use ILIAS\UI\Implementation\Component\Symbol as S;

class DurationInputTest extends ILIAS_UI_TestBase
{
    public function setUp() : void
    {
        $this->name_source = new DefNamesource();
        $this->data_factory = new Data\Factory();
        $this->factory = $this->buildFactory();
    }

    protected function buildLanguage()
    {
        if (!isset($this->lng)) {
            $this->lng = $this->createMock(\ilLanguage::class);
        }
        return $this->lng;
    }

    protected function buildRefinery()
    {
        return new \ILIAS\Refinery\Factory($this->data_factory, $this->buildLanguage());
    }

    protected function buildFactory()
    {
        return new ILIAS\UI\Implementation\Component\Input\Field\Factory(
            new SignalGenerator(),
            $this->data_factory,
            $this->buildRefinery(),
            $this->buildLanguage()
        );
    }
    public function getUIFactory()
    {
        $factory = new class extends NoUIFactory {
            public function symbol() : \ILIAS\UI\Component\Symbol\Factory
            {
                return new S\Factory(
                    new S\Icon\Factory(),
                    new S\Glyph\Factory(),
                    new S\Avatar\Factory()
                );
            }
        };
        return $factory;
    }

    public function test_withFormat()
    {
        $format = $this->data_factory->dateFormat()->germanShort();
        $duration = $this->factory->duration('label', 'byline')
            ->withFormat($format);

        $this->assertEquals(
            $format,
            $duration->getFormat()
        );
    }

    public function test_withMinValue()
    {
        $dat = new \DateTimeImmutable('2019-01-09');
        $duration = $this->factory->duration('label', 'byline')
            ->withMinValue($dat);

        $this->assertEquals(
            $dat,
            $duration->getMinValue()
        );
    }

    public function test_withMaxValue()
    {
        $dat = new \DateTimeImmutable('2019-01-09');
        $duration = $this->factory->duration('label', 'byline')
            ->withMaxValue($dat);

        $this->assertEquals(
            $dat,
            $duration->getMaxValue()
        );
    }

    public function test_withUseTime()
    {
        $datetime = $this->factory->duration('label', 'byline');
        $this->assertFalse($datetime->getUseTime());
        $this->assertTrue($datetime->withUseTime(true)->getUseTime());
    }

    public function test_withTimeOnly()
    {
        $datetime = $this->factory->duration('label', 'byline');
        $this->assertFalse($datetime->getTimeOnly());
        $this->assertTrue($datetime->withTimeOnly(true)->getTimeOnly());
    }

    public function test_withTimeZone()
    {
        $datetime = $this->factory->duration('label', 'byline');
        $this->assertNull($datetime->getTimeZone());
        $tz = 'Europe/Moscow';
        $this->assertEquals(
            $tz,
            $datetime->withTimeZone($tz)->getTimeZone()
        );
    }

    public function test_withInvalidTimeZone()
    {
        $this->expectException(\InvalidArgumentException::class);
        $datetime = $this->factory->duration('label', 'byline');
        $tz = 'NOT/aValidTZ';
        $datetime->withTimeZone($tz);
    }

    public function test_render()
    {
        $datetime = $this->factory->duration('label', 'byline');
        $r = $this->getDefaultRenderer();
        $html = $this->brutallyTrimHTML($r->render($datetime));

        $expected = $this->brutallyTrimHTML('
<div class="form-group row">
   <label for="id_1" class="control-label col-sm-3">label</label>
   <div class="col-sm-9">
      <div class="il-input-duration" id="id_1">
         <div class="form-group row">
            <label for="id_2" class="control-label col-sm-3">start</label>
            <div class="col-sm-9">
               <div class="input-group date il-input-datetime" id="id_2"><input type="text" name="" placeholder="YYYY-MM-DD" class="form-control form-control-sm" /><span class="input-group-addon"><a class="glyph" href="#" aria-label="calendar"><span class="glyphicon glyphicon-calendar" aria-hidden="true"></span></a></span></div>
            </div>
         </div>
         <div class="form-group row">
            <label for="id_3" class="control-label col-sm-3">end</label>
            <div class="col-sm-9">
               <div class="input-group date il-input-datetime" id="id_3"><input type="text" name="" placeholder="YYYY-MM-DD" class="form-control form-control-sm" /><span class="input-group-addon"><a class="glyph" href="#" aria-label="calendar"><span class="glyphicon glyphicon-calendar" aria-hidden="true"></span></a></span></div>
            </div>
         </div>
      </div>
      <div class="help-block">byline</div>
   </div>
</div>
');
        $this->assertEquals($expected, $html);
    }
}
