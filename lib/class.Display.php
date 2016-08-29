<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: Display
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
namespace Booker;

class Display
{
	private $mod; //Booker module-object reference
	private $utils; //Utils-class object
	private $rangefmt; //cache for translated string used in cell-tips

	public function __construct(&$mod)
	{
		$this->mod = $mod;
		$this->utils = new Utils();
	}

	/*
	GetLimits:
	@dts: datetime object representing start of 1st day of total report period
	@dte: datetime object representing start of 1st day AFTER total report period
	@$seglen: enum 0..3 representing report-segment length (i.e. per table column)
	@$slen: booking slot length (seconds)
	@starts: ascending-sorted array of start-available-block timestamps, maybe empty
	@ends: array of corresponding end-block timestamps, likewise maybe empty
	Returns: array with 3 members, each a seconds-offset
	 [0] = min offset i.e. from any seg start to 1st available block start in that seg
	 [1] = max seg-offset i.e. from any seg start to end of last available block in that segment
	 [2] = max period-offset i.e. from @dts to end of last available block in the period
	*/
	private function GetLimits($dts, $dte, $seglen, $slen, $starts, $ends)
	{
		$rels = array('+1 day','+7 days','+1 month','+1 year');
		$offs = $rels[$seglen];
		$dtw = clone $dts;

		if ($starts) {
			$segs = array();
			$sege = array();
			while ($dtw < $dte) {
				$segs[] = $dtw->getTimestamp();
				$dtw->modify($offs);
				$sege[] = $dtw->getTimestamp()-1;
			}

			$biggest = $sege[0] - $segs[0];
			$ob = $biggest;
			$oe = 0;
			$blocks = new Blocks();
			$iter = new \ArrayIterator($note);

			list($nots,$note) = $blocks->DiffBlocks($segs,$sege,$starts,$ends);
			foreach ($segs as $i=>$st) {
				//get smallest $note[] member > $st
				while ($iter->valid()) {
					$t = $iter->current();
					if ($t > $st) {
						if ($t < $sege[$i]) {
							$t -= $st;
							if ($t < $ob)
								$ob = $t;
						} else {
							$ob = 0;
						}
						break;
					}
					$iter->next();
				}
				//get biggest $nots[] member < $sege[$i]
				$st = $sege[$i];
				while ($iter->valid()) {
					$t = $iter->current();
					if ($t > $st) {
						$oe = $biggest;
						break;
					} elseif ($t == $st) {
						$j = $iter->key();
						$t = $nots[$j] - $segs[$i];
						if ($t > $oe)
							$oe = $t;
						break;
					}
					$iter->next();
				}
				if (!$iter->valid()) { //no more limits
					$j = end(array_keys($segs));
					if ($i != $j) { //more segment(s)
						$ob = 0;
						$oe = $biggest;
					}
					break;
				}
			}
			if ($ob < $biggest)
				$ob++;
			if ($oe > 0)
				$oe--;
			$oa = end($nots) - $segs[0];
		} else { //whole period is available
			$ob = 0;
			$st = $dts->getTimestamp();
			$dtw->modify($offs);
			$oe = $dtw->getTimestamp() - $st - 1;
			$oa = $dte->getTimestamp() - 1;
		}
		return array($ob,$oe,$oa);
	}

	/*
	GetSlotNames:
	@idata: reference to data array for item being processed
	@dts: datetime object representing start of 1st day of total report period
	@$seglen: enum 0..2 representing report-segment length (e.g. per table column)
	@offst: seconds-offset from segment start to start of 1st row (from GetLimits())
	@offnd: seconds-offset from segment start to one-past end of last row (OR maybe == end)
	@slen: booking slot length (seconds)
	@celloff: string representing cell coverage: '' for @slen, otherwise DateTime modifier '+1 X'
	Returns: array with a member for each relevant booking slot for the report segment
	*/
	private function GetSlotNames(&$idata, $dts, $offst, $offnd, $seglen, $slen, $celloff)
	{
		$dtw = clone $dts;
		$dt2 = clone $dts;

		switch ($seglen) {
		 case \Booker::SEGDAY: //day-per-column
			$dt2->modify('+1 day'); //segment limit
			$fmt = $idata['timeformat'] ? $idata['timeformat'] : 'G:i';
			break;
		 case \Booker::SEGWEEK: //week-per-column
			$t = $dts->format('w');
			if ($t > 0) //Sunday start
				$dtw->modify('-'.$t.' days'); //segment start
			$base = $dtw->format('Y-m-d');
			$dt2->modify($base.' +7 days'); //segment limit
			$fmt = $idata['dateformat'] ? $idata['dateformat'] : 'j M';
			$shortday = (strpos($fmt,'D') !== FALSE);
			$daynames = $this->utils->DayNames($this->mod,range(0,6),$shortday);
			break;
		 case \Booker::SEGMTH: //month-per-column
			$t = $dts->format('Y-m');
			$dtw->modify($t.'-1 0:0:0');
			$dt2->modify($t.'-1 0:0:0 +31 days'); //in this context, assume each reported month has max. # days
			break;
		}

		$esl = $dt2->getTimestamp() - $dts->getTimestamp(); //effective slot = whole-period
		if ($slen > $esl)
			$slen = $esl; //limit 'effective-slot' to report-segment-length
		$ss = $dtw->getTimestamp();
		$se = $ss + $offnd;
		$ss  += $offst;
		$cells = array();

		while ($ss < $se) {
			$dt2->setTimestamp($ss);
			$one = new \stdClass();
			switch ($seglen) {
			 case \Booker::SEGDAY:
				$one->data = $dt2->format($fmt);
				$one->iso = $dt2->format(' G:i');
				break;
			 case \Booker::SEGWEEK:
				$t = $dt2->format('w'); //day of week 0..6
				$one->data = $daynames[$t];
				$one->iso = $dt2->format('-d');
				break;
			 case \Booker::SEGMTH:
				$t = count($cells) + 1; //next integer = day of (longest) month 1..31
				$one->data = sprintf('%2d',$t);
				$one->iso = $dt2->format('-d');
				break;
			}
			$one->style = 'class="slotname"';
			$cells[] = $one;

			if ($celloff) {
				$dt2->modify($celloff);
				$ss = $dt2->getTimestamp();
			} else
				$ss += $slen;
		}
		return $cells;
	}

	/*
	GetTitles:
	Populate array of column-titles
	@idata: reference to array of data for item as per table-record, with inherited data where available
	@dts: datetime object representing start of 1st day of period for which titles are wanted
	@range: enum 0..3 representing the total span of the report period
	@seglen: enum 0..2 representing the duration of each report-segment (e.g. column)
	Returns: 2-member array,
	 [0] = array of column-title strings for public display
	 [1] = array of corresponding date-strings as YYYY-MM-DD, for lookup upon column-click
	*/
	private function GetTitles(&$idata, $dts, $range, $seglen)
	{
		$titles = array();
		$fmt = $idata['dateformat'] ? $idata['dateformat'] : 'j M';
		$shortday = (strpos($fmt,'D') !== FALSE);
		$longday = (strpos($fmt,'l') !== FALSE);
		$isos = array();
		switch ($range) {
		 case \Booker::RANGEDAY: //single-day-view
			$t = $dts->format('w'); //0 (for Sunday) .. 6 (for Saturday)
			$d = $this->utils->DayNames($this->mod,$t,$shortday);
			if ($shortday)
				$fmt = preg_replace('/(?<!\\\)D,?\w*/','',$fmt);
			if ($longday)
				$fmt = preg_replace('/(?<!\\\)l,?\w*/','',$fmt);
			$titles[] = $d.'<br />'.$dts->format($fmt);
			$isos[] = $dts->format('Y-m-d'); //rows must append ' G:i'
			break;
		 case \Booker::RANGEWEEK: //week-view
			$names = $this->utils->DayNames($this->mod,range(0,6),$shortday);
			if ($shortday)
				$fmt = preg_replace('/(?<!\\\)D,?\w*/','',$fmt);
			if ($longday)
				$fmt = preg_replace('/(?<!\\\)l,?\w*/','',$fmt);
			$dtw = clone $dts; //preserve $dts
			$t = $dtw->format('w'); //0 (for Sunday) .. 6 (for Saturday)
			$t1 = $t;
			do {
				$d = $names[$t];
				$titles[] = $d.'<br />'.$dtw->format($fmt);
				$isos[] = $dtw->format('Y-m-d');
				$t++;
				if ($t > 6)
					$t = 0;
				$dtw->modify('+1 day');
			} while ($t != $t1);
			break;
		 case \Booker::RANGEMTH: //month-view
			$dtw = clone $dts; //preserve $dts
			if ($seglen == \Booker::SEGDAY) { //day-per-column
				//show individual days
				$names = $this->utils->DayNames($this->mod,range(0,6),TRUE); //for 30-ish cols, force short name
				if ($shortday)
					$fmt = preg_replace('/(?<!\\\)D,?\w*/','',$fmt);
				if ($longday)
					$fmt = preg_replace('/(?<!\\\)l,?\w*/','',$fmt);
				$t = $dtw->format('j'); //1 to 31
				$l = $dtw->format('t'); //28 to 31
				$t1 = $t;
				do {
					$d = $dtw->format('w');
					$titles[] = $names[$d].'<br />'.$dtw->format($fmt);
					$isos[] = $dtw->format('Y-m-d');
					$t++;
					if ($t > $l)
						$t = 1;
					$dtw->modify('+1 day');
				} while ($t != $t1);
			} else { //$seglen == \Booker::SEGWEEK, week-per-column
				if ($shortday)
					$fmt = preg_replace('/(?<!\\\)D,?\w*/','',$fmt);
				if ($longday)
					$fmt = preg_replace('/(?<!\\\)l,?\w*/','',$fmt);
				//may need custom title for start of week including $start
				$w = $dtw->format('w');
				switch ($w) {
				 case 0:
					break;
				 case 1:
					$dtw->modify('-1 day');
				 default:
					$dtw->modify('-'.$w.' days');
					break;
				}
				//end-date
				$dt2 = clone $dts;
				$dt2->modify('+1 month');
				do {
					$titles[] = $dtw->format($fmt);
					$isos[] = $dtw->format('Y-m');  //rows must append '-d'
					$dtw->modify('+7 days');
				} while ($dtw < $dt2);
			}
			break;
		 case \Booker::RANGEYR: //year-view
			$dtw = clone $dts;
			if ($seglen == \Booker::SEGWEEK) { //week-per-column
				$w = $dtw->format('w');
				switch ($w) {
					case 0:
						break;
					case 1:
						$dtw->modify('-1 day');
					default:
						$dtw->modify('-'.$w.' days');
						break;
				}
				//end-date
				$dt2 = clone $dts;
				$dt2->modify('+1 year');
				do {
					$titles[] = $dtw->format($fmt);
					$isos[] = $dtw>format('Y-m');
					$dtw->modify('+7 days');
				} while ($dtw < $dt2);
			} else { //$seglen == \Booker::SEGMTH, month-per-column
				$shortmonth = (strpos($fmt,'M') !== FALSE);
				//$longmonth = (strpos($fmt,'F') !== FALSE);
				$names = $this->utils->MonthNames($this->mod,range(1,12),$shortmonth);
				$t = $dtw->format('n'); //1 to 12
				$t1 = $t;
				do {
					$titles[] = $names[$t].'<br />'.$dtw->format('Y');
					$isos[] = $dtw->format('Y-m');
					$t++;
					if ($t > 12)
						$t = 1;
					$dtw->modify('+1 month');
				} while ($t != $t1);
			}
			break;
		}
		return array($titles,$isos);
	}

	/*
	Coalesce:
	Merge overlapping slots in @slots c.f. Blocks::MergeBlocks($starts,$ends)
	@slots: array with members each an array($bs,$be) for slot start,end
	Returns: array with mergers done
	*/
	private function Coalesce($slots)
	{
		$c = count($slots);
		if ($c < 2)
			return $slots;
		usort($slots, function ($a, $b)
		{
			if ($a[0] == $b[0])
				return ($a[1] - $b[1]);
			return ($a[0] - $b[0]);
		});
		$i = 0;
		while ($i < $c) {
			$e1 = $slots[$i][1];
			for ($j=$i+1; $j<$c; $j++) {
				if (isset($slots[$j])) {
					if ($slots[$j][0] > $e1) {
						break;
					}
					$e2 = $slots[$j][1];
					if ($e2 > $e1) {
						$slots[$i][1] = $e2;
					}
					unset($slots[$j]);
				}
			}
			$i = $j;
		}
		return array_values($slots); //contiguous keys again
	}

	/*
	FillCell:
	Get object populated with data for a table-cell.

	@idata: reference to array of resource parameters
	@dt: UTC DateTime object
	@ss: UTC timestamp for start of cell (usually also slot start)
	@se: ditto for end of slot
	@celloff: string representing cell coverage: '' for slotlength,
		otherwise DateTime modifier '+1 X'
	@bookob: reference to 'iterable' array-object for all bookings data, with
		contents sorted (first) by booking-start ascending
	@position: starting iterator-position in @bookob
	@allresource: reference to array of all (possibly just 1) resource-ids
		which may be used and if so, included in the display
	@ufuncs: reference to Userops object
	Returns: 2-member array, 1st is the object, 2nd is replacement position for next call
	*/
	private function FillCell(&$idata, $dt, $ss, $se, $celloff, &$bookob, $position, &$allresource, &$ufuncs)
	{
		$iter = $bookob->getIterator();
		try {
			$iter->seek($position);
		} catch (Exception $e) {
			$one = new \stdClass();
			$one->data = NULL; //$e->getMessage();
			$one->style = 'class="vacant"';
			return array($one,$position);
		}

		$dtw = clone $dt; //preserve $dt
		if ($celloff) {
			$dtw->modify($celloff);
			$re = $dtw->getTimestamp();
		} else
			$re = $se;

		$resources = array(); //id(s) actually booked
		$bslots = array(); //bookings (automatically) sorted by increasing $bs
		$users = array(); //at most 2 members
		$displayclass = array(); //ditto
		$row = FALSE;
		$nextpos = -1; //we can cache a single position cuz array-rows are sorted on start-stamp
		//interrogate all bookings-data for the cell
		while (1) {
			$prevrow = $row;
			$row = $iter->current();
			$bs = (int)$row['slotstart'];
			$be = $bs + $row['slotlen'];
			if (($bs <= $ss && $be > $ss)
			 || ($bs > $ss && $be < $re)
			 || ($bs < $re && $be >= $re)) {
				//log relevant booking-slots
				$bslots[] = array($bs,$be);
				//log 1st such slot extending past end
				if ($be > $re && $nextpos == -1) {
					$nextpos = $position; //next time, start from this row
				}
				//log distinct users until count users > 1
				if (!isset($users[1])) {
					$t = $row['booker_id'];
					$n = $ufuncs->GetName($this->mod,$t);
					if (!in_array($n,$users)) {
						$users[] = $n;
						//log corresponding displayclass
						$displayclass[] = $ufuncs->GetDisplayClass($this->mod,$t);
					}
				}
				//log distinct resource id's
				$t = (int)$row['item_id'];
				if (!in_array($t,$resources)) {
					$resources[] = $t;
				}
				$iter->next();
				if ($iter->valid())
					$position++;
				else
					break;
			} elseif ($prevrow != FALSE) {
				$row = $prevrow; //backup to last relevant row
				break;
			}
		}

		$one = new \stdClass();
		if ($bslots) { //found booking(s)
			if (count($users) == 1) {
				$one->data = reset($users);
				$multi = FALSE;
			} else {
				$one->data = $this->mod->Lang('title_various');
				$multi = TRUE;
			}
			if (count($resources) < count($allresource)) {
				$one->data .= ' + '.$this->mod->Lang('title_vacancies');
				$whole = FALSE;
			} else {
				$bslots = self::Coalesce($bslots);
				$t = reset($bslots);
				$whole = ($t[0] < $ss + 60); //1-minute slop
				if ($whole) {
					$t = end($bslots);
					$whole = ($t[1] > $re - 60); //CHECKME intra-gap(s) OK?
				}
				if (!$whole) { //TODO not the whole cell where available
					$one->data .= ' + '.$this->mod->Lang('title_vacancies');
				}
			}

			$names = $this->mod->dbHandle->GetCol('SELECT name FROM '.$this->mod->ItemTable.
			' WHERE item_id IN('.implode(',',$resources).') AND active=1');
			$one->tip = implode(',',$names); //assume all have a name!

			if ($multi) {
				if ($whole)
					$one->style = 'class="fullm"';
				else
					$one->style = 'class="partm"';
//TODO	$one->bid = (int)$row['bkg_id'];
			} else { //single-user
				$dtw->setTimestamp($bslots[0][0]);
				$d = $this->utils->IntervalFormat($this->mod,$dtw,$idata['dateformat']);
				$fmt = $idata['timeformat'];
				if (!$fmt)
					$fmt = 'G:i';
				$t1 = $dtw->format($fmt);
				$slotf = end($bslots);
				$dtw->setTimestamp($slotf[1]);
				$t2 = $dtw->format($fmt);
				$one->tip .= '&#013;'.$d.'&#013;'.sprintf($this->rangefmt,$t1,$t2);

				$type = ($whole) ? 'full':'part';
				if ($displayclass[0])
					$one->style = 'class="'.$type.$displayclass[0].'"';
				else
					$one->style = 'class="'.$type.'"';
//ALWAYS discoverable		if (count($bslots) == 1) //1-user, 1-booking : make it discoverable
					$one->bid = (int)$row['bkg_id'];
			}
			//next-time start
			if ($nextpos > -1) {
				$position = $nextpos;
			}
			//otherwise, $position represents the last-processed row, or 1-past if available
		} else { //all vacant
			$one->data = NULL;
			$one->style = 'class="vacant"';
		  //$position unchanged
		}
		return array($one,$position);
	}

	/*
	FillTable:
	Populates array of data (for passing to smarty then template) representing cells
	in a table, to show bookings for a single resource over time interval represented
	by @range.
	Displayed times are localised from stored UTC values. No runtime availability checking.
	For intervals up to month, columns are for each day. For longer intevals, columns
	are for each month.
	@idata: array of data for item as per table-record, with inherited data where available
	@start: UTC timestamp for start of first day to be reported
	@range: enum 0..3 indicating span of report day..year (per Utils::DisplayIntervals())
	Returns: array of columns' data, each member being an array of cells' data,
	 first column has slot-titles
	*/
	private function FillTable(&$idata, $start, $range)
	{
		$slotlen = $this->utils->GetInterval($this->mod,$idata['item_id'],'slot');
		list($dts,$dte) = $this->utils->RangeStamps($start,$range); //$dte represents 1-past end of wanted range
		switch ($range) {
		 case \Booker::RANGEDAY:
		 case \Booker::RANGEWEEK:
			$seglen = \Booker::SEGDAY; //table-column period one day
			//$celloff: cell-coverage '' = slot, otherwise DateTime modifier like '+1 X'
			$celloff = ($slotlen < 84600) ? '':'+1 day'; //each cell spans min(slotlen,report period)
			break;
		 case \Booker::RANGEMTH:
			$seglen = \Booker::SEGDAY; //report divided into days
			$celloff = ($slotlen < 84600) ? '+1 hour':'+1 day'; //each cell :: min(hour,report period)
			break;
		 case \Booker::RANGEYR:
			$seglen = \Booker::SEGMTH; //report divided into months
			$celloff = ($slotlen < 84600) ? '+1 day':''; //each cell :: min(day,slotlen)
			break;
		}
		switch ($seglen) {
		 case \Booker::SEGWEEK: //week-per-column
			$t = $dts->format('w');
			if ($t > 0) //Sunday start
				$dts->modify('-'.$t.' days');
			$t = $dte->format('w');
			if ($t > 0)
				$dte->modify('+'.(7-$t).' days');
			break;
		 case \Booker::SEGMTH: //month-per-column
			$t = $dts->format('Y-m');
			$dts->modify($t.'-1 0:0:0');
			$t = $dte->format('Y-m');
			$dte->modify($t.'-1 0:0:0 +1 month');
			break;
		}
		$dtw = clone $dts;

		$item_id = (int)$idata['item_id'];
		$is_group = ($item_id >= \Booker::MINGRPID);
		if ($is_group)
			$allresource = $this->utils->GetGroupItems($this->mod,$item_id,TRUE); //include sub-groups
		else
			$allresource = array($item_id);

		//update respective last-processed-repeats dates, if relevant
		$funcs = new Schedule();
		foreach ($allresource as $one) {
			$funcs->UpdateRepeats($this->mod,$one,$dte);
		}
		//get availability-blocks
		$rules = $this->utils->GetOneHeritableProperty($this->mod,$item_id,'available');
		$rules = array_filter($rules); //omit empties
		if ($rules) {
			$funcs = new WhenRules($this->mod);
			$timeparms = $funcs->TimeParms($idata);
			list($starts,$ends) = $funcs->AllIntervals($rules[0],$dts,$dte,$timeparms); //proximal-rule-only, no ancestor-merging
		} else { //all available
			$starts = array();
			$ends = array();
		}
		//get offsets of each column's top- and bottom-row
		list($segoffst,$segoffnd,$rangeoffnd) = self::GetLimits($dts,$dte,$seglen,$slotlen,$starts,$ends);
		//populate column of row-titles
		$cells = self::GetSlotNames($idata,$dts,$segoffst,$segoffnd,$seglen,$slotlen,$celloff);

		//prepend top-left cell
		$one = new \stdClass();
		$one->data = NULL;
		$one->iso = NULL;
		$one->style = 'class="topleft"';
		array_unshift($cells,$one);
		$rc = count($cells); //includes header/titles row

		$columns = array();
		$columns[] = $cells;
		//populate titles array
		list($titles,$isos) = self::GetTitles($idata,$dts,$range,$seglen);
		$cc = count($titles);

		$funcs = new Bookingops();
		$booked = $funcs->GetTableBooked($this->mod,$allresource,$dts->getTimestamp(),$dte->getTimestamp()-1);
		if ($booked) {
			//setup iterator
			$bookob = new \ArrayObject($booked);
			$position = 0; //init array-iterator-position
		} else {
			$bookob = FALSE;
		}
		$funcs = new Userops();

		$this->rangefmt = $this->mod->Lang('showrange'); //cache for FillCell()
		$rels = array('+1 day','+7 days','+1 month','+1 year');
		$offs = $rels[$seglen]; //column-adjuster

		//other column(s)
		for ($c = 0; $c < $cc; $c++) {
			$cells = array();
			//title
			$one = new \stdClass();
			$one->data = $titles[$c];
			$one->iso = $isos[$c];
			$one->style = 'class="periodname"';
			$cells[] = $one;

			$ss = $dts->getTimestamp(); //start-stamp for current segment
			$ss += $segoffst; //start-stamp for 1st displayed slot in segment
			//iterate slots for this segment
			for ($r = 1; $r < $rc; $r++) {
				$se = $ss + $slotlen - 1; //end-stamp of current slot, maybe < end-of-cell
				$dtw->setTimestamp($ss);
				if ($bookob) {
					list($one,$position) = self::FillCell(
						$idata,$dtw,$ss,$se,$celloff,$bookob,$position,$allresource,$funcs);
				} else {
					$one = new \stdClass();
					$one->data = NULL;
					$one->style = 'class="vacant"';
				}
				$cells[] = $one;

				//skip to next cell start
				if ($celloff) {
					$dtw->modify($celloff);
					$ss = $dtw->getTimestamp();
				} else
					$ss += $slotlen;
			}
			$columns[] = $cells;
			//skip to next segment start
			$dts->modify($offs);
		}
		return $columns;
	}

	/*
	TextInterval:
	Get formatted datetime-interval string, primarily for list-display
	@dts: localised DateTime representing start of booking
	@dte: ditto for end of booking
	@range: segment-length enum 0 .. 3
	@majr_fmt: PHP date() format for possibly-shared (and so, not to be duplicated) component of reported string
	@minr_fmt: ditto for always-used component of returned string
	@rangefmt: PHP sprintf() format for returning like 'start to end'
	@$timegroup: boolean, TRUE for time-grouped segments >> major_part in string
	Returns: the string
	*/
	private function TextInterval($dts, $dte, $range, $majr_fmt, $minr_fmt, $rangefmt, $timegroup)
	{
			$st = $dts->format($majr_fmt);
			$nd = $dte->format($majr_fmt);
			$st2 = $dts->format($minr_fmt);
			$nd2 = $dte->format($minr_fmt);
			if ($st == $nd) {
				switch ($range) {
				 case \Booker::RANGEYR:
					return sprintf($rangefmt,$st.' '.$st2,$nd2);
				 case \Booker::RANGEMTH:
				 case \Booker::RANGEWEEK:
					if (!$timegroup)
						return sprintf($rangefmt,$st.' '.$st2,$nd2);
					else
						return sprintf($rangefmt,$st2,$nd2);
				 case \Booker::RANGEDAY:
					return sprintf($rangefmt,$st2,$nd2);
				}
			}
			return sprintf($rangefmt,$st.' '.$st2,$nd.' '.$nd2);
	}

	/*
	FillList:
	@idata: array of data for resource or group as per table-record, with inherited data where available
	@start: timestamp for start of first day to be reported
	@range: enum 0..3 indicating span of report day..year (per Utils::DisplayIntervals())
	Returns: array of sections' data, each member being an object with array of text-rows
	*/
	private function FillList(&$idata, $start, $range)
	{
		list($dts,$dte) = $this->utils->RangeStamps($start,$range);
		$dtw = clone $dts;
		$item_id = (int)$idata['item_id'];
		$is_group = ($item_id >= \Booker::MINGRPID);
		if ($is_group)
			$allresource = $this->utils->GetGroupItems($this->mod,$item_id);
		else
			$allresource = array($item_id);
		//update respective last-processed-repeats dates, if relevant
		$funcs = new Schedule();
		foreach ($allresource as $one) {
			$funcs->UpdateRepeats($this->mod,$one,$dte);
		}
		$funcs = new Bookingops();
		$lfmt = (int)$idata['listformat'];
		$booked = $funcs->GetListBooked($this->mod,$is_group,$allresource,
			$lfmt,$dts->getTimestamp(),$dte->getTimestamp()- 1);
		if ($booked) {
			$majr_fmt = $idata['dateformat']; //part of report  //c.f. Utils::IntervalFormat($mod,$format,$dts)
			$minr_fmt = $idata['timeformat']; //other part
			$rangefmt = $this->mod->Lang('showrange');
			switch ($lfmt) {
			 case \Booker::LISTUS:
			 case \Booker::LISTRS:
				$tkey = 'user';
				break;
			 case \Booker::LISTSR:
				$tkey = 'name';
				break;
//			 case \Booker::LISTSU:
			 default:
				$tkey = 'slotstart';
				switch ($range) {
				 case \Booker::RANGEDAY:
				 case \Booker::RANGEWEEK:
				 case \Booker::RANGEMTH:
 					$hfmt = $idata['dateformat']; //title-format, group by day  //c.f. Utils::IntervalFormat($mod,$format,$dts)
					break;
				 case \Booker::RANGEYR:
					$hfmt = 'n'; //group by month
					break;
				}
			}
			$sections = array();
			$title = chr(2).chr(3); //anything unused, not empty
			$oneset = FALSE;
			foreach ($booked as &$one) {
				$dts->setTimestamp($one['slotstart']);
				if ($tkey == 'slotstart')
					$t = $dts->format($hfmt);
				else
					$t = $one[$tkey];
				if ($t != $title) {
					if ($oneset) {
						$oneset->rows = $rows;
						$sections[] = $oneset;
					}
					$title = $t;
					$oneset = new \stdClass();
					if ($tkey != 'slotstart' || $range > \Booker::RANGEDAY) {
						if ($tkey == 'slotstart' && $range == \Booker::RANGEYR) //year special case
							$oneset->title = $dts->format('F Y'); //TODO translated month-name
						else
							$oneset->title = $t;
					} else
						$oneset->title = ''; //no need for repeated date for a single day
					$rows = array();
				}
				//populate
				$dte->setTimestamp($one['slotstart'] + $one['slotlen']);
				$t = self::TextInterval($dts,$dte,$range,$majr_fmt,$minr_fmt,$rangefmt,($tkey == 'slotstart'));
				switch ($lfmt) {
				 case \Booker::LISTUS:
					$txt = $t;
					if ($is_group) $txt .= ' :: '.$one['name'];
					break;
				 case \Booker::LISTRS:
					$txt = $t;
					$txt .= ' :: '.$one['user'];
					break;
				 case \Booker::LISTSR:
					$txt = $one['user'];
					if ($is_group) $txt .= ' :: '.$one['name'];
					$txt .= ' :: '.$t;
					break;
//				 case \Booker::LISTSU:
				 default:
					$txt = $t;
					if ($is_group) $txt .= ' :: '.$one['name'];
					$txt .= ' :: '.$one['user'];
					break;
				}
				$rows[] = $txt;
			}
			unset($one);
			if ($oneset) {
				$oneset->rows = $rows;
				$sections[] = $oneset;
			}
			return $sections;
		}
		return FALSE;
	}

	/**
	Tabulate:
	Populate @smarty vars for display of tabulated bookings-data for relevant range
	@tplvars: reference to array of template variables
	@idata: array of data for item as per table-record, with inherited data where available
	@start: UTC timestamp for start of first day to be reported
	@range: enum 0..3 indicating span of report day..year
	*/
	public function Tabulate(&$tplvars, &$idata, $start, $range)
	{
		$columns = self::FillTable($idata,$start,$range);
		if ($columns) {
			$tplvars['columns'] = $columns;
			$rc = count(reset($columns));
			$tplvars['rowcount'] = $rc;
			switch ($range) {
			 case \Booker::RANGEDAY:
				$tc = 'daily';
				break;
			 case \Booker::RANGEWEEK:
				$tc = 'weekly';
				break;
			 case \Booker::RANGEMTH:
				$tc = 'monthly';
				break;
			 case \Booker::RANGEYR:
				$tc = 'yearly';
				break;
			}
			$tplvars['tableclass'] = $tc;
		} else {
			$tplvars['nobookings'] = $this->mod->Lang('nodata'); //should never happen
		}
	}

	/**
	Listify:
	Populate @smarty vars for display of list-style bookings-data for relevant range
	@tplvars: reference to array of template variables
	@idata: array of data for item as per table-record, with inherited data where available
	@start: UTC timestamp for start of first day to be reported
	@range: enum 0..3 indicating span of report day..year
	*/
	public function Listify(&$tplvars, &$idata, $start, $range)
	{
		$sections = self::FillList($idata,$start,$range);
		$tplvars['sections'] = $sections; //maybe empty
		if (!$sections)
			$tplvars['nobookings'] = $this->mod->Lang('nodata');
	}

}
