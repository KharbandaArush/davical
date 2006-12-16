<?php
/**
* Class for parsing RRule and getting us the dates
*
* @package   rscds
* @subpackage   caldav
* @author    Andrew McMillan <andrew@catalyst.net.nz>
* @copyright Catalyst .Net Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/

$ical_weekdays = array( 'SU' => 0, 'MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6 );

/**
* A Class for handling dates in iCalendar format.  We do make the simplifying assumption
* that all date handling in here is normalised to GMT.  One day we might provide some
* functions to do that, but for now it is done externally.
*
* @package awl
*/
class iCalDate {
  /**#@+
  * @access private
  */

  /** Text version */
  var $_text;

  /** Epoch version */
  var $_epoch;

  /** Fragmented parts */
  var $_yy;
  var $_mm;
  var $_dd;
  var $_hh;
  var $_mm;
  var $_ss;
  var $_tz;

  /** Which day of the week does the week start on */
  var $_wkst;

  /**#@-*/

  /**
  * The constructor takes either a text string formatted as an iCalendar date, or
  * epoch seconds.
  */
  function iCalDate( $input ) {
    $this->_wkst = 1; // Monday
    if ( preg_match( '/^\d{8}T\d{6}$/', $input ) ) {
      $this->SetLocalDate($input);
    }
    else if ( preg_match( '/^\d{8}T\d{6}Z$/', $input ) ) {
      $this->SetGMTDate($input);
    }
    else if ( intval($input) == 0 ) {
      return;
    }
    else {
      $this->SetEpochDate($input);
    }
  }


  /**
  * Set the date from a text string
  */
  function SetGMTDate( $input ) {
    $this->_text = $input;
    $this->_PartsFromText();
    $this->_GMTEpochFromParts();
  }


  /**
  * Set the date from a text string
  */
  function SetLocalDate( $input ) {
    $this->_text = $input;
    $this->_PartsFromText();
    $this->_EpochFromParts();
  }


  /**
  * Set the date from an epoch
  */
  function SetEpochDate( $input ) {
    $this->_epoch = intval($input);
    $this->_TextFromEpoch();
    $this->_PartsFromText();
  }


  /**
  * Given an epoch date, convert it to text
  */
  function _TextFromEpoch() {
    $this->_text = date('Ymd\THis', $this->_epoch );
    dbg_error_log( "RRule", " Text %s from epoch %d", $this->_text, $this->_epoch );
  }

  /**
  * Given a GMT epoch date, convert it to text
  */
  function _GMTTextFromEpoch() {
    $this->_text = gmdate('Ymd\THis', $this->_epoch );
    dbg_error_log( "RRule", " Text %s from epoch %d", $this->_text, $this->_epoch );
  }

  /**
  * Given a text date, convert it to parts
  */
  function _PartsFromText() {
    $this->_yy = intval(substr($this->_text,0,4));
    $this->_mo = intval(substr($this->_text,4,2));
    $this->_dd = intval(substr($this->_text,6,2));
    $this->_hh = intval(substr($this->_text,9,2));
    $this->_mi = intval(substr($this->_text,11,2));
    $this->_ss = intval(substr($this->_text,13,2));
  }


  /**
  * Given a GMT text date, convert it to an epoch
  */
  function _GMTEpochFromParts() {
    $this->_epoch = gmmktime ( $this->_hh, $this->_mi, $this->_ss, $this->_mo, $this->_dd, $this->_yy );
    dbg_error_log( "RRule", " Epoch %d from %04d-%02d-%02d %02d:%02d:%02d", $this->_epoch, $this->_yy, $this->_mo, $this->_dd, $this->_hh, $this->_mi, $this->_ss );
  }


  /**
  * Given a local text date, convert it to an epoch
  */
  function _EpochFromParts() {
    $this->_epoch = mktime ( $this->_hh, $this->_mi, $this->_ss, $this->_mo, $this->_dd, $this->_yy );
    dbg_error_log( "RRule", " Epoch %d from %04d-%02d-%02d %02d:%02d:%02d", $this->_epoch, $this->_yy, $this->_mo, $this->_dd, $this->_hh, $this->_mi, $this->_ss );
  }


  /**
  * Set the day of week used for calculation of week starts
  */
  function SetWeekStart() {
    global $ical_weekdays;
    $this->_wkst = $ical_weekdays[$weekstart];
  }


  /**
  * Set the day of week used for calculation of week starts
  */
  function Render( $fmt = 'Y-m-d H:i:s' ) {
    return date( $fmt, $this->_epoch );
  }


  /**
  * No of days in a month 1(Jan) - 12(Dec)
  */
  function DaysInMonth( $mo=false, $yy=false ) {
    if ( $mo === false ) $mo = $this->_mo;
    switch( $mo ) {
      case  1: // January
      case  3: // March
      case  5: // May
      case  7: // July
      case  8: // August
      case 10: // October
      case 12: // December
        return 31;
        break;

      case  4: // April
      case  6: // June
      case  9: // September
      case 11: // November
        return 30;
        break;

      case  2: // February
        if ( $yy === false ) $yy = $this->_yy;
        if ( (($yy % 4) == 0) && ((($yy % 100) != 0) || (($yy % 400) == 0) ) ) return 29;
        return 28;
        break;

      default:
        dbg_error_log( "ERROR"," Invalid month of '%s' passed to DaysInMonth", $mo );
        break;

    }
  }


  /**
  * Set the day in the month to what we have been given
  */
  function SetMonthDay( $dd ) {
    if ( $dd == $this->_dd ) return; // Shortcut
    $dd = min($dd,$this->DaysInMonth());
    $this->_dd = $dd;
    $this->_EpochFromParts();
    $this->_TextFromEpoch();
  }


  /**
  * Add some number of months to a date
  */
  function AddMonths( $mm ) {
    dbg_error_log( "RRule", " Adding %d months to %s", $mm, $this->_text );
    $this->_mo += $mm;
    while ( $this->_mo < 1 ) {
      $this->_mo += 12;
      $this->_yy--;
    }
    while ( $this->_mo > 12 ) {
      $this->_mo -= 12;
      $this->_yy++;
    }

    if ( ($this->_dd > 28 && $this->_mo == 2) || $this->_dd > 30 ) {
      // Ensure the day of month is still reasonable
      $dim = $this->DaysInMonth();
      if ( $this->_dd > $dim ) {
        // We don't need to check for month > 12, since _dd can't be greater than 31 if it was previously valid
        $this->_mo++;
        $this->_dd -= $dim;
      }
    }
    $this->_EpochFromParts();
    $this->_TextFromEpoch();
    dbg_error_log( "RRule", " Added %d months and got %s", $mm, $this->_text );
  }


  /**
  * Add some integer number of days to a date
  */
  function AddDays( $dd ) {
    dbg_error_log( "RRule", " Adding %d days to %s", $dd, $this->_text );
    $this->_dd += $dd;
    while ( 1 > $this->_dd ) {
      $this->_mo--;
      if ( $this->_mo < 1 ) {
        $this->_mo += 12;
        $this->_yy--;
      }
      $this->_dd += $this->DaysInMonth();
    }
    while ( ($dim = $this->DaysInMonth($this->_mo)) < $this->_dd ) {
      $this->_dd -= $dim;
      $this->_mo++;
      if ( $this->_mo > 12 ) {
        $this->_mo -= 12;
        $this->_yy++;
      }
    }
    $this->_EpochFromParts();
    $this->_TextFromEpoch();
    dbg_error_log( "RRule", " Added %d days and got %s", $dd, $this->_text );
  }


  /**
  * Add duration
  */
  function AddDuration( $duration ) {
    list( $sign, $days, $time ) = preg_split( '/[PT]/', $duration );
    $sign = ( $sign == "-" ? -1 : 1);
    if ( preg_match( '/(\d)+(D|W)/', $days, $matches ) ) {
      $days = intval($matches[1]);
      if ( $matches[2] == 'W' ) $days *= 7;
      $this->AddDays( $days * $sign );
    }
    if ( preg_match( '/(\d)+(H)/', $time, $matches ) )  $hh = $matches[1];
    if ( preg_match( '/(\d)+(M)/', $time, $matches ) )  $mi = $matches[1];
    if ( preg_match( '/(\d)+(S)/', $time, $matches ) )  $ss = $matches[1];

    $this->_hh += ($hh * $sign);
    $this->_mi += ($mi * $sign);
    $this->_ss += ($ss * $sign);

    if ( $this->_ss < 0 ) {  $this->_mi -= (intval(abs($this->ss/60))+1); $this->_ss += ((intval(abs($this->mi/60))+1) * 60); }
    if ( $this->_ss > 59) {  $this->_mi += (intval(abs($this->ss/60))+1); $this->_ss -= ((intval(abs($this->mi/60))+1) * 60); }
    if ( $this->_mi < 0 ) {  $this->_hh -= (intval(abs($this->mi/60))+1); $this->_mi += ((intval(abs($this->mi/60))+1) * 60); }
    if ( $this->_mi > 59) {  $this->_hh += (intval(abs($this->mi/60))+1); $this->_mi -= ((intval(abs($this->mi/60))+1) * 60); }
    if ( $this->_hh < 0 ) {  $this->AddDays( -1 * (intval(abs($this->_hh/24))+1) );  $this->_hh += ((intval(abs($this->_hh/24))+1)*24);  }
    if ( $this->_hh > 23) {  $this->AddDays( (intval(abs($this->_hh/24))+1) );       $this->_hh -= ((intval(abs($this->_hh/24))+1)*24);  }

    $this->_EpochFromParts();
    $this->_TextFromEpoch();
  }


  /**
  * Produce an iCalendar format DURATION for the difference between this an another iCalDate
  *
  * @param date $from The start of the period
  * @return string The date difference, as an iCalendar duration format
  */
  function DateDifference( $from ) {
    if ( !is_object($from) ) {
      $from = new iCalDate($from);
    }
    if ( $from->_epoch < $this->_epoch ) {
      /** One way to simplify is to always go for positive differences */
      return( "-". $from->DateDifference( &$self ) );
    }
//    if ( $from->_yy == $this->_yy && $from->_mo == $this->_mo ) {
      /** Also somewhat simpler if we can use seconds */
      $diff = $from->_epoch - $this->_epoch;
      $result = "";
      if ( $diff > 86400) {
        $result = intval($diff / 86400);
        $diff = $diff % 86400;
        if ( $diff == 0 && (($result % 7) == 0) ) {
          // Duration is an integer number of weeks.
          $result .= intval($result / 7) . "W";
          return $result;
        }
        $result .= "D";
      }
      $result = "P".$result."T";
      if ( $diff > 3600) {
        $result .= intval($diff / 3600) . "H";
        $diff = $diff % 3600;
      }
      if ( $diff > 60) {
        $result .= intval($diff / 60) . "M";
        $diff = $diff % 60;
      }
      if ( $diff > 0) {
        $result .= intval($diff) . "S";
      }
      return $result;
//    }

/**
* From an intense reading of RFC2445 it appears that durations which are not expressible
* in Weeks/Days/Hours/Minutes/Seconds are invalid.
*  ==> This code is not needed then :-)
    $yy = $from->_yy - $this->_yy;
    $mo = $from->_mo - $this->_mo;
    $dd = $from->_dd - $this->_dd;
    $hh = $from->_hh - $this->_hh;
    $mi = $from->_mi - $this->_mi;
    $ss = $from->_ss - $this->_ss;

    if ( $ss < 0 ) {  $mi -= 1;   $ss += 60;  }
    if ( $mi < 0 ) {  $hh -= 1;   $mi += 60;  }
    if ( $hh < 0 ) {  $dd -= 1;   $hh += 24;  }
    if ( $dd < 0 ) {  $mo -= 1;   $dd += $this->DaysInMonth();  } // Which will use $this->_(mo|yy) - seemingly sensible
    if ( $mo < 0 ) {  $yy -= 1;   $mo += 12;  }

    $result = "";
    if ( $yy > 0) {    $result .= $yy."Y";   }
    if ( $mo > 0) {    $result .= $mo."M";   }
    if ( $dd > 0) {    $result .= $dd."D";   }
    $result .= "T";
    if ( $hh > 0) {    $result .= $hh."H";   }
    if ( $mi > 0) {    $result .= $mi."M";   }
    if ( $ss > 0) {    $result .= $ss."S";   }
    return $result;
*/
  }

  /**
  * Test to see if our _mo matches something in the list of months we have received.
  * @param string $monthlist A comma-separated list of months.
  * @return boolean Whether this date falls within one of those months.
  */
  function TestByMonth( $monthlist ) {
    if ( !isset($monthlist) ) return true;  // If BYMONTH is not specified any month is OK
    dbg_error_log( "RRule", " Testing BYMONTH %s against month %d", $monthlist, $this->_mo );
    $months = array_flip(split( ',',$monthlist ));
    return isset($months[$this->_mo]);
  }

  /**
  * Applies any BYDAY to the month to return a set of days
  * @param string $byday The BYDAY rule
  * @return array An array of the day numbers for the month which meet the rule.
  */
  function GetMonthByDay($byday) {
    dbg_error_log( "RRule", " Applying BYDAY %s to month", $byday );
    $days_in_month = $this->DaysInMonth();
    $dayrules = split(',',$byday);
    $set = array();
    $first_dow = (date('w',$this->_epoch) - $this->_dd + 36) % 7;
    foreach( $dayrules AS $k => $v ) {
      $days = $this->MonthDays($first_dow,$days_in_month,$v);
      foreach( $days AS $k2 => $v2 ) {
        $set[$v2] = 1;
      }
    }
    return $set;
  }

  /**
  * Applies any BYMONTHDAY to the month to return a set of days
  * @param string $bymonthday The BYMONTHDAY rule
  * @return array An array of the day numbers for the month which meet the rule.
  */
  function GetMonthByMonthDay($bymonthday) {
    dbg_error_log( "RRule", " Applying BYMONTHDAY %s to month", $bymonthday );
    $days_in_month = $this->DaysInMonth();
    $dayrules = split(',',$byday);
    $set = array();
    $first_dow = (date('w',$this->_epoch) - $this->_dd + 36) % 7;
    foreach( $dayrules AS $k => $v ) {
      $v = intval($v);
      if ( $v > 0 && $v <= $days_in_month ) $set[$v] = 1;
    }
    return $set;
  }

  /**
  * Test if $this is greater than the date parameter
  * @param string $lesser The other date, as a local time string
  * @return boolean True if $this > $lesser
  */
  function GreaterThan($lesser) {
    return ( $this->_text > $lesser );  // These sorts of dates are designed that way...
  }


  /**
  * Given a MonthDays string like "1MO", "-2WE" return an integer day of the month.
  *
  * @param string $dow_first The day of week of the first of the month.
  * @param string $days_in_month The number of days in the month.
  * @param string $dayspec The specification for a month day (or days) which we parse.
  *
  * @return array An array of the day numbers for the month which meet the rule.
  */
  function &MonthDays($dow_first, $days_in_month, $dayspec) {
    global $ical_weekdays;
    dbg_error_log( "RRule", " Getting days for '%s'. %d days starting on a %d", $dayspec, $days_in_month, $dow_first );
    $set = array();
    preg_match( '/([0-9-]*)(MO|TU|WE|TH|FR|SA|SU)/', $dayspec, $matches);
    $numeric = intval($matches[1]);
    $dow = $ical_weekdays[$matches[2]];

    $first_matching_day = 1 + ($dow - $dow_first);
    if ( $first_matching_day < 0 ) $first_matching_day += 7;

    dbg_error_log( "RRule", " Looking at %d for first match on (%s/%s), %d for numeric", $first_matching_day, $matches[1], $matches[2], $numeric );

    while( $first_matching_day <= $days_in_month ) {
      $set[] = $first_matching_day;
      $first_matching_day += 7;
    }

    if ( $numeric != 0 ) {
      if ( $numeric < 0 ) {
        $numeric += count($set);
      }
      else {
        $numeric--;
      }
      $answer = $set[$numeric];
      $set = array( $answer );
    }

    dbg_log_array( "RRule", 'MonthDays', $set, false );

    return $set;
  }

}



/**
* A Class for handling Events on a calendar which repeat
*
* Here's the spec, from RFC2445:
*
     recur      = "FREQ"=freq *(

                ; either UNTIL or COUNT may appear in a 'recur',
                ; but UNTIL and COUNT MUST NOT occur in the same 'recur'

                ( ";" "UNTIL" "=" enddate ) /
                ( ";" "COUNT" "=" 1*DIGIT ) /

                ; the rest of these keywords are optional,
                ; but MUST NOT occur more than once

                ( ";" "INTERVAL" "=" 1*DIGIT )          /
                ( ";" "BYSECOND" "=" byseclist )        /
                ( ";" "BYMINUTE" "=" byminlist )        /
                ( ";" "BYHOUR" "=" byhrlist )           /
                ( ";" "BYDAY" "=" bywdaylist )          /
                ( ";" "BYMONTHDAY" "=" bymodaylist )    /
                ( ";" "BYYEARDAY" "=" byyrdaylist )     /
                ( ";" "BYWEEKNO" "=" bywknolist )       /
                ( ";" "BYMONTH" "=" bymolist )          /
                ( ";" "BYSETPOS" "=" bysplist )         /
                ( ";" "WKST" "=" weekday )              /
                ( ";" x-name "=" text )
                )

     freq       = "SECONDLY" / "MINUTELY" / "HOURLY" / "DAILY"
                / "WEEKLY" / "MONTHLY" / "YEARLY"

     enddate    = date
     enddate    =/ date-time            ;An UTC value

     byseclist  = seconds / ( seconds *("," seconds) )

     seconds    = 1DIGIT / 2DIGIT       ;0 to 59

     byminlist  = minutes / ( minutes *("," minutes) )

     minutes    = 1DIGIT / 2DIGIT       ;0 to 59

     byhrlist   = hour / ( hour *("," hour) )

     hour       = 1DIGIT / 2DIGIT       ;0 to 23

     bywdaylist = weekdaynum / ( weekdaynum *("," weekdaynum) )

     weekdaynum = [([plus] ordwk / minus ordwk)] weekday

     plus       = "+"

     minus      = "-"

     ordwk      = 1DIGIT / 2DIGIT       ;1 to 53

     weekday    = "SU" / "MO" / "TU" / "WE" / "TH" / "FR" / "SA"
     ;Corresponding to SUNDAY, MONDAY, TUESDAY, WEDNESDAY, THURSDAY,
     ;FRIDAY, SATURDAY and SUNDAY days of the week.

     bymodaylist = monthdaynum / ( monthdaynum *("," monthdaynum) )

     monthdaynum = ([plus] ordmoday) / (minus ordmoday)

     ordmoday   = 1DIGIT / 2DIGIT       ;1 to 31

     byyrdaylist = yeardaynum / ( yeardaynum *("," yeardaynum) )

     yeardaynum = ([plus] ordyrday) / (minus ordyrday)

     ordyrday   = 1DIGIT / 2DIGIT / 3DIGIT      ;1 to 366

     bywknolist = weeknum / ( weeknum *("," weeknum) )

     weeknum    = ([plus] ordwk) / (minus ordwk)

     bymolist   = monthnum / ( monthnum *("," monthnum) )

     monthnum   = 1DIGIT / 2DIGIT       ;1 to 12

     bysplist   = setposday / ( setposday *("," setposday) )

     setposday  = yeardaynum
*
* At this point we are going to restrict ourselves to parts of the RRULE specification
* seen in the wild.  And by "in the wild" I don't include within people's timezone
* definitions.  We always convert time zones to canonical names and assume the lower
* level libraries can do a better job with them than we can.
*
* We will concentrate on:
*  FREQ=(YEARLY|MONTHLY|WEEKLY|DAILY)
*  UNTIL=
*  COUNT=
*  INTERVAL=
*  BYDAY=
*  BYMONTHDAY=
*  BYSETPOS=
*  WKST=
*  BYYEARDAY=
*  BYWEEKNO=
*  BYMONTH=
*
*
* @package awl
*/
class RRule {
  /**#@+
  * @access private
  */

  /** The first instance */
  var $_first;

  /** The current instance pointer */
  var $_current;

  /** An array of all the dates so far */
  var $_dates;

  /** Whether we have calculated any of the dates */
  var $_started;

  /** Whether we have calculated all of the dates */
  var $_finished;

  /** The rule, in all it's glory */
  var $_rule;

  /** The rule, in all it's parts */
  var $_part;

  /**#@-*/

  /**
  * The constructor takes a start & end date and an RRULE definition.  All of these
  * follow the iCalendar standard.
  */
  function RRule( $start, $rrule ) {
    $this->_first = new iCalDate($start);
    $this->_finished = false;
    $this->_started = false;
    $this->_dates = array( $this->_first );
    $this->_current = -1;

    $this->_rule = preg_replace( '/\s/m', '', $rrule);
    if ( substr($this->_rule, 0, 6) == 'RRULE:' ) {
      $this->_rule = substr($this->_rule, 6);
    }
    $parts = split(';',$this->_rule);
    $this->_part = array();
    foreach( $parts AS $k => $v ) {
      list( $type, $value ) = split( '=', $v, 2);
      dbg_error_log( "RRule", " Parts of %s split into %s and %s", $v, $type, $value );
      $this->_part[$type] = $value;
    }

    // A little bit of validation
    if ( !isset($this->_part['FREQ']) ) {
      dbg_error_log( "ERROR", " RRULE MUST have FREQ=value (%s)", $rrule );
    }
    if ( isset($this->_part['COUNT']) && isset($this->_part['UNTIL'])  ) {
      dbg_error_log( "ERROR", " RRULE MUST NOT have both COUNT=value and UNTIL=value (%s)", $rrule );
    }
    if ( isset($this->_part['COUNT']) && intval($this->_part['COUNT']) < 1 ) {
      dbg_error_log( "ERROR", " RRULE MUST NOT have both COUNT=value and UNTIL=value (%s)", $rrule );
    }
    if ( !preg_match( '/(YEAR|MONTH|WEEK|DAI)LY/', $this->_part['FREQ']) ) {
      dbg_error_log( "ERROR", " RRULE Only FREQ=(YEARLY|MONTHLY|WEEKLY|DAILY) are supported at present (%s)", $rrule );
    }
    if ( ! isset($this->_part['INTERVAL']) ) $this->_part['INTERVAL'] = 1;
    if ( $this->_part['FREQ'] == "YEARLY" ) {
      $this->_part['INTERVAL'] *= 12;
      $this->_part['FREQ'] = "MONTHLY";
    }
    if ( $this->_part['FREQ'] == "WEEKLY" ) {
      $this->_part['INTERVAL'] *= 7;
      $this->_part['FREQ'] = "DAILY";
    }
  }

  /**
  * This is most of the meat of the RRULE processing, where we find the next date.
  * We maintain an
  */
  function &GetNext( ) {
    $next = $this->_dates[$this->_current];
    $this->_current++;

    /**
    * If we have already found some dates we may just be able to return one of those.
    */
    if ( isset($this->_dates[$this->_current]) ) {
      return $this->_dates[$this->_current];
    }
    else {
      if ( isset($this->_part['COUNT']) && $this->_current >= $this->_part['COUNT'] ) // >= since _current is 0-based and COUNT is 1-based
        $this->_finished = true;
      if ( $this->_finished ) {
        $next = null;
        return $next;
      }
    }

    $days = array();
    if ( isset($this->_part['WKST']) ) $next->SetWeekStart($this->_part['WKST']);
    if ( $this->_part['FREQ'] == "MONTHLY" ) {
      $limit = 100;
      do {
        $limit--;
        do {
          $limit--;
          if ( $this->_started ) {
            $next->AddMonths($this->_part['INTERVAL']);
          }
          else {
            $this->_started = true;
          }
        }
        while ( $limit && ! $next->TestByMonth($this->_part['BYMONTH']) );

        if ( isset($this->_part['BYDAY']) ) {
          $days = $next->GetMonthByDay($this->_part['BYDAY']);
        }
        else if ( isset($this->_part['BYMONTHDAY']) ) {
          $days = $next->GetMonthByMonthDay($this->_part['BYMONTHDAY']);
        }
        else
          $days[$next->_dd] = 1;

        if ( isset($this->_part['BYSETPOS']) ) {
          $days = $next->ApplyBySetpos($this->_part['BYSETPOS'], $days);
        }

      }
      while( $limit && count($days) < 1 );

    }
    else if ( $this->_part['FREQ'] == "DAILY" ) {
      $limit = 100;
      do {
        $limit--;
        if ( $this->_started ) {
          $next->AddDays($this->_part['INTERVAL']);
        }
        else {
          $this->_started = true;
        }
        $days[$next->_dd] = 1;
      }
      while( $limit && count($days) < 1 );
    }

    $i = 0;
    foreach( $days AS $day => $v ) {
      $next->SetMonthDay($day);
      if ( isset($this->_part['UNTIL']) && $next->GreaterThan($this->_part['UNTIL']) ) {
        $this->_finished = true;
        continue;
      }
      /** FIXME should check this is greater than the first date here too */
      $this->_dates[$this->_current + $i] = $next;
      $i++;
    }

    if ( isset($this->_dates[$this->_current]) ) {
      return $this->_dates[$this->_current];
    }
    else {
      $next = null;
      return $next;
    }
  }

}

?>