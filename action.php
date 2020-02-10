<?php
/**
 * DokuWiki Plugin booking (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <dokuwiki@cosmocode.de>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) {
    die();
}

class action_plugin_booking extends DokuWiki_Action_Plugin
{
    /** @var helper_plugin_booking */
    protected $helper;

    protected $issuperuser = false;

    public function __construct()
    {
        $this->helper = plugin_load('helper', 'booking');
    }


    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     *
     * @return void
     */
    public function register(Doku_Event_Handler $controller)
    {
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'handle_ajax_call_unknown');

    }

    /**
     * [Custom event handler which performs action]
     *
     * Called for event:
     *
     * @param Doku_Event $event event object by reference
     * @param mixed $param [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     *
     * @return void
     */
    public function handle_ajax_call_unknown(Doku_Event $event, $param)
    {
        if ($event->data != 'plugin_booking') return;
        $event->preventDefault();
        $event->stopPropagation();

        $id = getID();
        $perm = auth_quickaclcheck($id);
        if ($perm < AUTH_READ) {
            http_status('403', 'Bookings not accessible to you');
            exit;
        }
        if ($perm == AUTH_ADMIN) $this->issuperuser = true;

        if (isset($_REQUEST['do'])) {
            if ($_REQUEST['do'] == 'book') {
                $this->addBooking($id, $_REQUEST['date'] . ' ' . $_REQUEST['time'], $_REQUEST['length']);
            } elseif ($_REQUEST['do'] == 'cancel') {
                $this->cancelBooking($id, (int)$_REQUEST['at']);
            } elseif ($_REQUEST['do'] == 'csv' && $this->issuperuser) {
                $this->exportCSV($id);
                exit();
            }

        }

        $this->outputHTML($id);
    }

    /**
     * Create a Booking
     *
     * @param string $id
     * @param string $start
     * @param string $length
     */
    protected function addBooking($id, $start, $length)
    {
        if (!$_SERVER['REMOTE_USER']) return;

        try {
            $this->helper->addBooking($id, $start, $length, $_SERVER['REMOTE_USER']);
            msg($this->getLang('booked'), 1);
        } catch (Exception $e) {
            msg($this->getLang('exception' . $e->getCode()), -1);
        }
    }

    /**
     * Cancel a booking
     *
     * @param string $id
     * @param int $start
     */
    protected function cancelBooking($id, $start)
    {
        if ($this->issuperuser) {
            $user = null;
        } else {
            $user = $_SERVER['REMOTE_USER'];
        }

        if ($this->helper->cancelBooking($id, $start, $user)) {
            msg($this->getLang('cancelled'), 1);
        } else {
            msg($this->getLang('notcancelled'), -1);
        }
    }

    /**
     * Export all bookings as CSV
     *
     * @param string $id
     */
    protected function exportCSV($id)
    {
        header('Content-Type', 'text/csv;charset=utf-8');
        header('Content-Disposition: attachment;filename=' . noNS($id) . '.csv');
        $bookings = $this->helper->getBookings($id);

        $out = fopen('php://output', 'w');
        fputcsv($out, ['start', 'end', 'user']);
        foreach ($bookings as $booking) {
            fputcsv(
                $out,
                [
                    date('Y-m-d H:i', $booking['start']),
                    date('Y-m-d H:i', $booking['end']),
                    $booking['user']
                ]
            );
        }
        fclose($out);
    }

    /**
     * Create the HTML output
     *
     * @param string $id
     */
    protected function outputHTML($id)
    {
        header('Content-Type', 'text/html;charset=utf-8');

        html_msgarea();

        if ($_SERVER['REMOTE_USER']) {
            $this->showForm();
        }

        $this->listBookings($id);
    }

    /**
     * Display the booking form
     */
    protected function showForm()
    {
        $form = new dokuwiki\Form\Form();
        $form->addFieldsetOpen($this->getLang('headline'));
        $form->addTextInput('date')
            ->attrs(['type' => 'date', 'min' => date('Y-m-d'), 'required' => 'required'])
            ->addClass('edit');
        $form->addTextInput('time')
            ->attrs(['type' => 'time', 'required' => 'required'])
            ->val(date('H', time() + 60 * 60) . ':00')
            ->addClass('edit');
        $form->addTextInput('length')
            ->attrs(['required' => 'required', 'placeholder' => '1h'])
            ->val('1h')
            ->addClass('edit');
        $form->addButton('submit', $this->getLang('book'));
        $form->addFieldsetClose();
        echo $form->toHTML();
    }

    /**
     * Display the current bookings
     *
     * @param string $id
     */
    protected function listBookings($id)
    {
        $bookings = $this->helper->getBookings($id, time());
        echo '<table>';
        foreach ($bookings as $booking) {
            echo '<tr>';

            echo '<td>';
            echo dformat($booking['start']) . ' - ' . dformat($booking['end']);
            echo '</td>';

            echo '<td>';
            echo userlink($booking['user']);
            echo '</td>';

            echo '<td>';
            if ($booking['user'] == $_SERVER['REMOTE_USER'] || $this->issuperuser) {
                echo '<a href="#' . $booking['start'] . '" class="cancel">' . $this->getLang('cancel') . '</a>';
            } else {
                echo '&nbsp;';
            }
            echo '</td>';

            echo '</tr>';
        }
        echo '</table>';

        if ($this->issuperuser) {
            echo '<a href="' . DOKU_BASE . 'lib/exe/ajax.php?call=plugin_booking&do=csv">' . $this->getLang('csv') . '</a>';
        }
    }
}

