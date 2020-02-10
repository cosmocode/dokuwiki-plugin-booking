<?php

/**
 * General tests for the booking plugin
 *
 * @group plugin_booking
 * @group plugins
 */
class booking_plugin_booking_test extends DokuWikiTest
{

    protected $pluginsEnabled = ['booking'];

    public function test_createbooking()
    {
        global $conf;

        /** @var helper_plugin_booking $hlp */
        $hlp = plugin_load('helper', 'booking');

        try {
            $hlp->addBooking('test:book', '2020-12-17 13:00', '1.5h', 'andi');
            $ok = true;
        } catch (Exception $e) {
            $ok = false;
        }
        $this->assertTrue($ok);

        $this->assertFileExists($conf['metadir'].'/test/book.booking');
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionCode helper_plugin_booking::E_NOLENGTH
     */
    public function test_nolength()
    {
        /** @var helper_plugin_booking $hlp */
        $hlp = plugin_load('helper', 'booking');

        $hlp->addBooking('test:book', '2020-12-17 13:00', 'foobar', 'andi');
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionCode helper_plugin_booking::E_OVERLAP
     */
    public function test_overlapbooking()
    {
        /** @var helper_plugin_booking $hlp */
        $hlp = plugin_load('helper', 'booking');

        $hlp->addBooking('test:book', '2020-12-17 13:00', '1.5h', 'andi');
        $hlp->addBooking('test:book', '2020-12-17 13:20', '1.5h', 'andi');
    }

}
