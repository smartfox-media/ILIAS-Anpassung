<?php

/* Copyright (c) 1998-2010 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once 'Services/Calendar/interfaces/interface.ilCalendarScheduleFilter.php';

/**
 * Calendar schedule filter for exercises
 *
 * @author Jörg Lützenkirchen <luetzenkirchen@leifos.com>
 * @version $Id$
 *
 * @ingroup ServicesCalendar
 */
class ilCalendarScheduleFilterExercise implements ilCalendarScheduleFilter
{
	protected $user_id; // [int]
	/**
	 * @var \ilLogger
	 */
	protected $logger; // [Logger]
	
	public function __construct($a_user_id)
	{
		global $DIC;

		$this->user_id = $a_user_id;
		$this->logger = $DIC->logger()->exc();
	}

	/**
	 * @return \ilLogger
	 */
	public function getLogger()
	{
		return $this->logger;
	}	
	
	public function filterCategories(array $a_cats)
	{			
		return $a_cats;
	}
	
	public function modifyEvent(ilCalendarEntry $a_event)
	{
		include_once './Services/Calendar/classes/class.ilCalendarCategoryAssignments.php';
		$cal_cat = $this->isExerciseCategory(ilCalendarCategoryAssignments::_lookupCategory($a_event->getEntryId()));
		if($cal_cat)
		{			
			$exc_obj_id = $cal_cat->getObjId();
			
			// see ilExAssignment::handleCalendarEntries()
			$context_id = $a_event->getContextId();
			$subtype = (int)substr($context_id, -1);
			$ass_id = (int)substr($context_id, 0, -1);
			
			// 1 is peer review deadline
			if($subtype != 1)
			{
				include_once './Modules/Exercise/classes/class.ilExAssignment.php';
				$ass = new ilExAssignment($ass_id);
				if($ass->getExerciseId() == $exc_obj_id)
				{
					$idl = $ass->getPersonalDeadline($this->user_id);
					if($idl &&
						$idl != $ass->getDeadline())
					{
						// we have individal deadline (see addCustomEvents());
						return false;				
					}					
				}
			}
		}
		
		return $a_event;
	}

	public function addCustomEvents(ilDate $start, ilDate $end, array $a_categories)
	{
		$all_events = array();
		
		foreach($a_categories as $cat_id)
		{
			$cal_cat = $this->isExerciseCategory($cat_id);
			if(!$cal_cat)
			{
				continue;
			}
			
			$exc_obj_id = $cal_cat->getObjId();
			
			include_once './Services/Calendar/classes/class.ilCalendarCategoryAssignments.php';
			include_once './Modules/Exercise/classes/class.ilExAssignment.php';
			foreach(ilExAssignment::getInstancesByExercise($exc_obj_id) as $ass)
			{
				$idl = $ass->getPersonalDeadline($this->user_id);
				if($idl &&
					$idl != $ass->getDeadline())
				{
					$idl = new ilDateTime($idl, IL_CAL_UNIX);					
					if(ilDateTime::_within($idl, $start, $end))
					{
						include_once './Services/Calendar/classes/class.ilCalendarEntry.php';
						$app_ids = ilCalendarCategoryAssignments::_getAssignedAppointments(array($cal_cat->getCategoryID()));
						foreach($app_ids as $app_id)
						{
							include_once './Services/Calendar/classes/class.ilCalendarEntry.php';
							$entry = new ilCalendarEntry($app_id);
							if(!$entry->isAutoGenerated())
							{
								continue;
							}

							if($entry->getContextId() == $ass->getId()."0")
							{
								$entry->setStart($idl);
								$entry->setEnd($idl);
								$all_events[] = $entry;				
							}																							
						}						
					}
				}
			}
		}
		
		return $all_events;
	}
	
	/**
	 * Check valid exercise calendar category
	 * 
	 * @param int $a_cat_id
	 * @return ilCalendarCategory
	 */
	protected function isExerciseCategory($a_cat_id)
	{
		include_once './Services/Calendar/classes/class.ilCalendarCategory.php';
		$category = ilCalendarCategory::getInstanceByCategoryId($a_cat_id);
		
		if($category->getType() != ilCalendarCategory::TYPE_OBJ)
		{
			$this->getLogger()->debug('Not modifying calendar for non object type');
			return false;
		}
		if($category->getObjType() != 'exc')
		{
			$this->getLogger()->debug('Category object type is != folder => category event not modified');
			return false;
		}		
		
		return $category;
	}
}
