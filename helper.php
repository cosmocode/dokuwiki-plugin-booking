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
        $file = $conf['metadir'] .'/'. utf8_encodeFN(str_replace(':', '/', "$id.booking"));
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
                if ($end < $from) continue;
            } else {
                // list all beginning at start
                if ($start < $from) continue;
            }

            $bookings[] = [$start, $end, $user];
        }

        fclose($fh);

        return $bookings;
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

}

