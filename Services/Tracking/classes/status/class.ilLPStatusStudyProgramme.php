<?php
/* Copyright (c) 1998-2010 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
* @author Richard Klees <richard.klees@concepts-and-training.de>
*
* @version $Id: class.ilLPStatusCollectionManual.php 40252 2013-03-01 12:21:49Z jluetzen $
*
* @package ilias-tracking
*
*/

class ilLPStatusStudyProgramme extends ilLPStatus
{
    public static function _getCountInProgress($a_obj_id)
    {
        return count(self::_getInProgress($a_obj_id));
    }
    
    public static function _getInProgress($a_obj_id)
    {
        $prg = new ilObjStudyProgramme($a_obj_id, false);
        return $prg->getIdsOfUsersWithNotCompletedAndRelevantProgress();
    }
    
    public static function _getCountCompleted($a_obj_id)
    {
        return count(self::_getCompleted($a_obj_id));
    }
    
    public static function _getCompleted($a_obj_id)
    {
        $prg = new ilObjStudyProgramme($a_obj_id, false);
        return $prg->getIdsOfUsersWithCompletedProgress();
    }

    public static function _getFailed($a_obj_id)
    {
        $prg = new ilObjStudyProgramme($a_obj_id, false);
        return $prg->getIdsOfUsersWithFailedProgress();
    }

    public function determineStatus($a_obj_id, $a_user_id, $a_obj = null)
    {
        $prg = new ilObjStudyProgramme($a_obj_id, false);
        $progresses = $prg->getProgressesOf($a_user_id);

        $relevant = false;
        $failed = false;

        foreach ($progresses as $progress) {
            if ($progress->isSuccessful()) {
                if (!$progress->isSuccessfulExpired()) {
                    return ilLPStatus::LP_STATUS_COMPLETED_NUM;
                } else {
                    $failed = true;
                }
            }
            if ($progress->isRelevant()) {
                $relevant = true;
            }
            if ($progress->isFailed()) {
                $failed = true;
            }
        }
        if ($failed) {
            return ilLPStatus::LP_STATUS_FAILED_NUM;
        }
        if ($relevant) {
            return ilLPStatus::LP_STATUS_IN_PROGRESS_NUM;
        }

        return ilLPStatus::LP_STATUS_NOT_ATTEMPTED_NUM;
    }
}
