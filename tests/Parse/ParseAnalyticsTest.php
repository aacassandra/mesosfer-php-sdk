<?php

namespace Parse\Test;

use Parse\ParseAnalytics;

use PHPUnit\Framework\TestCase;

class ParseAnalyticsTest extends TestCase
{
    public static function setUpBeforeClass() : void
    {
        Helper::setUp();
    }

    public function tearDown() : void
    {
        Helper::tearDown();
    }

    public function assertAnalyticsValidation($event, $params, $expectedJSON)
    {
        // We'll test that the event encodes properly, and that the analytics call
        // doesn't throw an exception.
        $json = ParseAnalytics::_toSaveJSON($params ?: []);
        $this->assertEquals($expectedJSON, $json);
        ParseAnalytics::track($event, $params ?: []);
    }

    public function testTrackEvent()
    {
        $expected = '{"dimensions":{}}';
        $this->assertAnalyticsValidation('testTrackEvent', null, $expected);
    }

    public function testFailsOnEventName1()
    {
        $this->expectException(
            'Exception',
            'A name for the custom event must be provided.'
        );
        ParseAnalytics::track('');
    }

    public function testFailsOnEventName2()
    {
        $this->expectException(
            'Exception',
            'A name for the custom event must be provided.'
        );
        ParseAnalytics::track('    ');
    }

    public function testFailsOnEventName3()
    {
        $this->expectException(
            'Exception',
            'A name for the custom event must be provided.'
        );
        ParseAnalytics::track("    \n");
    }

    public function testTrackEventDimensions()
    {
        $expected = '{"dimensions":{"foo":"bar","bar":"baz"}}';
        $params = [
            'foo' => 'bar',
            'bar' => 'baz',
        ];
        $this->assertAnalyticsValidation('testDimensions', $params, $expected);

        $date = date(DATE_RFC3339);
        $expected = '{"dimensions":{"foo":"bar","bar":"baz","someDate":"'.
            $date.'"}}';
        $params = [
            'foo'      => 'bar',
            'bar'      => 'baz',
            'someDate' => $date,
        ];
        $this->assertAnalyticsValidation('testDate', $params, $expected);
    }

    public function testBadKeyDimension()
    {
        $this->expectException(
            '\Exception',
            'Dimensions expected string keys and values.'
        );
        ParseAnalytics::track('event', [1=>'good-value']);
    }

    public function testBadValueDimension()
    {
        $this->expectException(
            '\Exception',
            'Dimensions expected string keys and values.'
        );
        ParseAnalytics::track('event', ['good-key'=>1]);
    }
}
