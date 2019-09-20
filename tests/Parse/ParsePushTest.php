<?php

namespace Parse\Test;

use Parse\ParseInstallation;
use Parse\ParsePush;
use Parse\ParsePushStatus;

use PHPUnit\Framework\TestCase;

class ParsePushTest extends TestCase
{
    public static function setUpBeforeClass() : void
    {
        Helper::setUp();
    }

    public function tearDown() : void
    {
        Helper::tearDown();
    }

    public function testNoMasterKey()
    {
        $this->expectException('\Parse\ParseException');

        ParsePush::send(
            [
                'channels' => [''],
                'data'     => ['alert' => 'sample message'],
            ]
        );
    }

    public function testBasicPush()
    {
        $response = ParsePush::send(
            [
            'channels' => [''],
            'data'     => ['alert' => 'sample message'],
            ],
            true
        );
        $this->assertEquals($response['result'], 1);
    }

    /**
     * @group parse-push
     */
    public function testMissingWhereAndChannels()
    {
        $this->expectException(
            '\Parse\ParseException',
            "Sending a push requires either \"channels\" or a \"where\" query."
        );

        ParsePush::send([
            'data'  => [
                'alert' => 'are we missing something?'
            ]
        ], true);
    }

    /**
     * @group parse-push
     */
    public function testWhereAndChannels()
    {
        $this->expectException(
            '\Parse\ParseException',
            "Channels and query can not be set at the same time."
        );

        $query = ParseInstallation::query();
        $query->equalTo('key', 'value');

        ParsePush::send([
            'data'      => [
                'alert'     => 'too many limits'
            ],
            'channels'  => [
                'PushFans',
                'PHPFans'
            ],
            'where'     => $query
        ], true);
    }

    public function testPushToQuery()
    {
        $query = ParseInstallation::query();
        $query->equalTo('key', 'value');
        $response = ParsePush::send(
            [
            'data'  => ['alert' => 'iPhone 5 is out!'],
            'where' => $query,
            ],
            true
        );
        $this->assertEquals($response['result'], 1);
    }

    public function testPushToQueryWithoutWhere()
    {
        $query = ParseInstallation::query();
        $response = ParsePush::send(
            [
                'data'  => ['alert' => 'Done without conditions!'],
                'where' => $query,
            ],
            true
        );
        $this->assertEquals($response['result'], 1);
    }

    public function testNonQueryWhere()
    {
        $this->expectException(
            '\Exception',
            'Where parameter for Parse Push must be of type ParseQuery'
        );
        ParsePush::send(
            [
                'data'  => ['alert' => 'Will this really work?'],
                'where' => 'not-a-query',
            ],
            true
        );
    }

    public function testPushDates()
    {
        $response = ParsePush::send(
            [
                'data'            => ['alert' => 'iPhone 5 is out!'],
                'push_time'       => new \DateTime(),
                'expiration_time' => new \DateTime(),
                'channels'        => [],
            ],
            true
        );
        $this->assertEquals($response['result'], 1);
    }

    public function testExpirationTimeAndIntervalSet()
    {
        $this->expectException(
            '\Exception',
            'Both expiration_time and expiration_interval can\'t be set.'
        );
        ParsePush::send(
            [
                'data'            => ['alert' => 'iPhone 5 is out!'],
                'push_time'       => new \DateTime(),
                'expiration_time' => new \DateTime(),
                'expiration_interval'   => 90,
                'channels'        => [],
            ],
            true
        );
    }

    /**
     * @group push-status
     */
    public function testPushHasHeaders()
    {
        $response = ParsePush::send(
            [
                'channels' => [''],
                'data'     => ['alert' => 'sample message'],
            ],
            true
        );

        // verify headers are present
        $this->assertArrayHasKey('_headers', $response);
    }

    /**
     * @group push-status
     */
    public function testGettingPushStatus()
    {
        $env = config('app.env');
        $payload = [
            'alert' => 'sample message'
        ];

        $response = ParsePush::send(
            [
                'channels' => [''],
                'data'     => $payload,
            ],
            true
        );

        // verify push status id is present
        $this->assertTrue(isset($response['_headers'][config('mesosfer.' . $env . '.headerPushStatusId')]));

        // verify ParsePush indicates there is a push status id as well
        $this->assertTrue(ParsePush::hasStatus($response));

        // get the _PushStatus object
        $pushStatus = ParsePush::getStatus($response);

        $this->assertNotNull($pushStatus);

        // verify values
        $this->assertTrue(
            $pushStatus->getPushTime() instanceof \DateTime,
            'Push time was not as expected'
        );

        $query = $pushStatus->getPushQuery();
        $options = $query->_getOptions();
        $this->assertEquals([
            'where' => [
                'channels'  => [
                    '$in'       => [
                        ''
                    ]
                ]
            ]
        ], $options);

        // verify payload
        $this->assertEquals(
            $payload,
            $pushStatus->getPushPayload(),
            'Payload did not match'
        );

        // verify source
        $this->assertEquals(
            "rest",
            $pushStatus->getPushSource(),
            'Source was not rest'
        );

        // verify not scheduled
        $this->assertFalse($pushStatus->isScheduled());

        // verify not pending
        $this->assertFalse($pushStatus->isPending());

        // verify 'running', or 'failed'/'succeeded' on later versions of parse-server
        // both are acceptable
        $this->assertTrue(
            $pushStatus->isRunning() || $pushStatus->hasFailed() || $pushStatus->hasSucceeded(),
            'Push was not running/succeeded/failed, was '.$pushStatus->getPushStatus()
        );

        // verify # sent & failed
        $this->assertEquals(
            0,
            $pushStatus->getPushesSent(),
            'More than 0 pushes sent'
        );
        $this->assertEquals(
            0,
            $pushStatus->getPushesFailed(),
            'More than 0 pushes failed'
        );

        $this->assertNotNull(
            $pushStatus->getPushHash(),
            'Hash not present'
        );

        if ($pushStatus->hasFailed()) {
            // verify we have not succeeded
            $this->assertFalse($pushStatus->hasSucceeded());
        } else {
            // verify we have succeeded (later servers)
            $this->assertTrue($pushStatus->hasSucceeded());
        }
    }

    /**
     * @group push-status
     */
    public function testGettingNonExistentPushStatus()
    {
        $pushStatus = ParsePushStatus::getFromId('not-a-real-id');
        $this->assertNull($pushStatus);
    }

    public function testDoesNotHaveStatus()
    {
        $this->assertFalse(ParsePush::hasStatus([]));
    }

    public function testGetStatus()
    {
        $env = config('app.env');
        // test no headers
        $this->assertNull(ParsePush::getStatus([]));

        // test no push id
        $this->assertNull(ParsePush::getStatus([
            '_headers'  => []
        ]));

        // test bad push status id
        $this->assertNull(ParsePush::getStatus([
            '_headers'  => [
                config('mesosfer.' . $env . '.headerPushStatusId')    => 'not-a-real-id'
            ]
        ]));
    }
}
