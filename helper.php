<?php
/**
 * DokuWiki Plugin booking (Helper Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <dokuwiki@cosmocode.de>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) {
    die();
}

class helper_plugin_booking extends DokuWiki_Plugin
{
    const E_NOLENGTH = 1;
    const E_OVERLAP = 2;

    // List of columns to display
    protected $columns = [ 'startend', 'user' ];

    // Labels for each displayed column
    protected $labels;
    
    public function getColumns()
    {
        return $this->columns;
    }

    public function getLabels()
    {
        return $this->labels;
    }

    public function setLabels($labels)
    {
        $this->labels = $labels;
    }
    
    /**
     * Get the filename where the booking data is stored for this resource
     *
     * @param string $id
     * @return string
     */
    public function getFile($id)
    {
        global $conf;
        $id = cleanID($id);
        $file = $conf['metadir'] . '/' . utf8_encodeFN(str_replace(':', '/', "$id.booking"));
        return $file;
    }

    /**
     * Get all existing bookings for a given resource
     *
     * @param string $id Page ID of the resource
     * @param int $from List bookings from this timestamp onwards (0 for all)
     * @param int $to List bookings up to this timestamp (0 for all)
     * @return array
     */
    public function getBookings($id, $from = 0, $to = 0)
    {
        $file = $this->getFile($id);
        if (!file_exists($file)) return [];

        $fh = fopen($file, 'r');
        if (!$fh) return [];

        $bookings = [];

        while (($line = fgets($fh, 4096)) !== false) {
            $line = trim($line);
            if ($line === '') continue;
            list($start, $end, $user) = explode("\t", $line, 3);
            if ($to) {
                // list all overlapping bookings
                if ($start > $to) continue;
                if ($end <= $from) continue;
            } else {
                // list all bookings that have not been ended at $from
                if ($end < $from) continue;
            }

            // we use the start time as index, for sorting
            $bookings[$start] = [
                'start' => $start,
                'end' => $end,
                'user' => $user];
        }

        fclose($fh);

        ksort($bookings);
        return $bookings;
    }


    /**
     * Wrap a string with a given HTML wrapper
     *
     * @param string $text the string to be wrapped
     * @param string $wrapper the HTML wrapper to apply to the string
     * @return string The wrapped string
     */
    public function htmlWrap($text, $wrapper, $class='')
    {
        if ($class !== '') {
            $output = "<{$wrapper} class=\"{$class}\">{$text}</{$wrapper}>";
        } else {
            $output = "<{$wrapper}>{$text}</{$wrapper}>";
        }
        return $output;
    }

    /**
     * Wrap string of table rows with heading, and table header and footer
     *
     * @param string $rows
     * @param string $heading
     * @param string $use_header_row
     * @return string Returns full table html as a string
     */    
    public function tableWrap($rows, $heading, $use_header_row=true)
    {
        // wrap table row html with heading, header, and footer
        $prefix = $this->htmlWrap($heading, 'h3');
        $prefix .= $this->tableHeader($use_header_row);
        $output = $prefix . $rows . '</table>';
        return $output;
    }
    
    
    /**
     * Construct table header for a booking as a string
     *
     * @param string $use_header_row
     * @return string Returns table header as a string
     */
    public function tableHeader($use_header_row=true)
    {	
        $theader = '';
        if ($use_header_row == true) {
            foreach(array_combine($this->columns, $this->labels) as $column => $label) {
                $theader .= $this->htmlWrap($label, 'th', $column);
            }
            $theader .= $this->htmlWrap('&nbsp;', 'td', 'cancel');
            $theader = $this->htmlWrap($theader, 'tr');
        }
	
        $theader = '<table class="inline">'. $theader;
	
        return $theader;
    }

    /**
     * Construct HTML for a table row for a booking as a string
     *
     * @param string $booking
     * @param string $use_cancel_link
     * @param string $cancel_string
     * @return string Returns table row HTML as a string
     */
    public function tableRow($booking, $use_cancel_link=false,
                             $cancel_string='cancel')
    {
        $trow = '';
        foreach($this->columns as $column) {
            switch($column) {
            case "startend":
                $tcell = dformat($booking['start']) . ' - ' . dformat($booking['end']);
                break;
            case "user":
                $tcell = userlink($booking['user']);
                break;
            }
            $tcell = $this->htmlWrap($tcell, 'td', $column);
            $trow = $trow . $tcell;
        }
        
        if ($use_cancel_link == true) {
            $tcancel = "<a href=\"#{$booking['start']}\" class=\"cancel\">{$cancel_string}</a>";
        } else {
            $tcancel = '&nbsp;';
        }
        $tcancel = $this->htmlWrap($tcancel, 'td', 'cancel');
        $trow = $trow . $tcancel;
        $trow = $this->htmlWrap($trow, 'tr');

        return $trow;
    }    

    /**
     * Parses simple time length strings to seconds
     *
     * @param string $time
     * @return int Returns 0 when the time could not be parsed
     */
    public function parseTime($time)
    {
        $time = trim($time);

        if (preg_match('/([\d\.,]+)([dhm])/i', $time, $m)) {
            $val = floatval(str_replace(',', '.', $m[1]));
            $unit = strtolower($m[2]);

            // convert to seconds
            if ($unit === 'd') {
                $val = $val * 60 * 60 * 8;
            } elseif ($unit === 'h') {
                $val = $val * 60 * 60;
            } else {
                $val = $val * 60;
            }

            return (int)$val;
        }

        return 0;
    }

    /**
     * Adds a booking
     *
     * @param string $id resource to book
     * @param string $begin strtotime compatible start datetime
     * @param string $length length of booking
     * @param string $user user doing the booking
     * @throws Exception when a booking can't be added
     */
    public function addBooking($id, $begin, $length, $user)
    {
        $file = $this->getFile($id);
        io_makeFileDir($file);

        $start = strtotime($begin);
        $end = $start + $this->parseTime($length);
        if ($start == $end) throw new \Exception('No valid length specified', self::E_NOLENGTH);

        $conflicts = $this->getBookings($id, $start, $end);
        if ($conflicts) throw new \Exception('Existing booking overlaps', self::E_OVERLAP);

        $line = "$start\t$end\t$user\n";
        file_put_contents($file, $line, FILE_APPEND);
    }

    /**
     * Cancel a booking
     *
     * The booking line is replaced by spaces in the file
     *
     * @param string $id The booking resource
     * @param string|int $at The start time of the booking to cancel. Use int for timestamp
     * @param string|null $user Only cancel if the user matches, null for no check
     * @return bool Was any booking canceled?
     */
    public function cancelBooking($id, $at, $user=null)
    {
        $file = $this->getFile($id);
        if (!file_exists($file)) return false;

        $fh = fopen($file, 'r+');
        if (!$fh) return false;

        // we support ints and time strings
        if (!is_int($at)) {
            $at = strtotime($at);
        }

        while (($line = fgets($fh, 4096)) !== false) {
            list($start, ,$booker) = explode("\t", $line, 3);
            if ($start != $at) continue;
            if ($user && $user != trim($booker)) continue;

            $len = strlen($line); // length of line (includes newline)
            fseek($fh, -1 * $len, SEEK_CUR); // jump back to beginning of line
            fwrite($fh, str_pad('', $len - 1)); // write spaces instead (keep new line)
            return true;
        }
        fclose($fh);

        return false;
    }
}

