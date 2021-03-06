<?php

/* Copyright (c) 2015 Richard Klees <richard.klees@concepts-and-training.de> Extended GPL, see docs/LICENSE */

/**
 * Class ilStudyProgrammeIndividualPlanTableGUI
 *
 * @author: Richard Klees <richard.klees@concepts-and-training.de>
 *
 */

class ilStudyProgrammeIndividualPlanTableGUI extends ilTable2GUI
{
    const SEL_COLUMN_COMPLETION_DATE = "completion_date";
    const SEL_COLUMN_DEADLINE = "prg_deadline";
    const SEL_COLUMN_ASSIGNMENT_DATE = "assignment_date";

    protected $assignment;
    /**
     * @var ilStudyProgrammeProgressDB
     */
    protected $sp_user_progress_db;

    public function __construct(
        ilObjStudyProgrammeIndividualPlanGUI $a_parent_obj,
        ilStudyProgrammeAssignment $a_ass,
        ilStudyProgrammeProgressRepository $sp_user_progress_db
    ) {
        $this->setId("manage_indiv");

        $this->sp_user_progress_db = $sp_user_progress_db;

        parent::__construct($a_parent_obj, 'manage');

        global $DIC;
        $ilCtrl = $DIC['ilCtrl'];
        $lng = $DIC['lng'];
        $ilDB = $DIC['ilDB'];
        $this->ctrl = $ilCtrl;
        $this->lng = $lng;
        $this->db = $ilDB;

        $this->assignment = $a_ass;

        $this->setEnableTitle(true);
        $this->setTopCommands(false);
        $this->setEnableHeader(true);
        // TODO: switch this to internal sorting/segmentation
        $this->setExternalSorting(false);
        $this->setExternalSegmentation(false);
        $this->setRowTemplate("tpl.individual_plan_table_row.html", "Modules/StudyProgramme");
        $this->setDefaultOrderDirection("asc");

        $this->getParentObject()->appendIndividualPlanActions($this);

        $columns = array( "status"
                        , "title"
                        , "prg_points_current"
                        , "prg_points_required"
                        , "prg_manual_status"
                        , "prg_possible"
                        , "prg_changed_by"
                        , "prg_completion_by"
                        );

        foreach ($this->getSelectedColumns() as $column) {
            $columns[] = $column;
        }

        foreach ($columns as $lng_var) {
            $this->addColumn($lng->txt($lng_var));
        }

        $plan = $this->fetchData();

        $this->setMaxCount(count($plan));
        $this->setData($plan);

        $this->determineLimit();
        $this->determineOffsetAndOrder();

        $this->possible_image = "<img src='" . ilUtil::getImagePath("icon_ok.svg") . "' alt='ok'>";
        $this->not_possible_image = "<img src='" . ilUtil::getImagePath("icon_not_ok.svg") . "' alt='not ok'>";
    }

    protected function fillRow($a_set)
    {
        $this->tpl->setVariable("STATUS", $a_set['status_repr']);

        $title = $a_set["title"];
        if ($a_set["program_status"] == ilStudyProgrammeSettings::STATUS_DRAFT) {
            $title .= " (" . $this->lng->txt("prg_status_draft") . ")";
        } elseif ($a_set["program_status"] == ilStudyProgrammeSettings::STATUS_OUTDATED) {
            $title .= " (" . $this->lng->txt("prg_status_outdated") . ")";
        }

        $this->tpl->setVariable("TITLE", $title);
        $this->tpl->setVariable("POINTS_CURRENT", $a_set["points_current"]);
        $this->tpl->setVariable("POINTS_REQUIRED", $this->getRequiredPointsInput($a_set["progress_id"], $a_set["status"], $a_set["points_required"]));
        $this->tpl->setVariable("MANUAL_STATUS", $this->getManualStatusSelect(
            $a_set["progress_id"],
            $a_set["status"]
        ));
        $this->tpl->setVariable("POSSIBLE", $a_set["possible"] ? $this->possible_image : $this->not_possible_image);
        $this->tpl->setVariable("CHANGED_BY", $a_set["changed_by"]);
        $this->tpl->setVariable("COMPLETION_BY", $a_set["completion_by"]);

        foreach ($this->getSelectedColumns() as $column) {
            switch ($column) {
                case self::SEL_COLUMN_ASSIGNMENT_DATE:
                    $this->tpl->setCurrentBlock("assignment_date");
                    $this->tpl->setVariable("ASSIGNMENT_DATE", $a_set["assignment_date"]);
                    $this->tpl->parseCurrentBlock("assignment_date");
                    break;
                case self::SEL_COLUMN_DEADLINE:
                    $this->tpl->setCurrentBlock("deadline");
                    $this->tpl->setVariable("DEADLINE", $this->getDeadlineInput($a_set["progress_id"], $a_set["deadline"]));
                    $this->tpl->parseCurrentBlock("deadline");
                    break;
                case self::SEL_COLUMN_COMPLETION_DATE:
                    $this->tpl->setCurrentBlock("completion_date");
                    $this->tpl->setVariable("COMPLETION_DATE", $a_set["completion_date"]);
                    $this->tpl->parseCurrentBlock("completion_date");
                    break;
            }
        }
    }

    /**
     * Get selectable columns
     *
     * @return array[] 	$cols
     */
    public function getSelectableColumns()
    {
        $cols = array();

        $cols[self::SEL_COLUMN_ASSIGNMENT_DATE] = array(
                "txt" => $this->lng->txt("assignment_date"));
        $cols[self::SEL_COLUMN_DEADLINE] = array(
                "txt" => $this->lng->txt("prg_deadline"));
        $cols[self::SEL_COLUMN_COMPLETION_DATE] = array(
                "txt" => $this->lng->txt("completion_date"));
        return $cols;
    }

    protected function fetchData()
    {
        $prg_id = $this->assignment->getRootId();
        $prg = ilObjStudyProgramme::getInstanceByObjId($prg_id);

        $ass_id = $this->assignment->getId();
        $usr_id = $this->assignment->getUserId();
        $plan = array();
        
        $prg->applyToSubTreeNodes(
            function ($node) use ($prg_id, $ass_id, $usr_id, &$plan, $prg) {
                $progress = $this->sp_user_progress_db->getByIds($node->getId(), $ass_id);
                $completion_by_id = $progress->getCompletionBy();

                if ($completion_by_id) {
                    $completion_by = ilObjUser::_lookupLogin($completion_by_id);
                    if (!$completion_by) {
                        $type = ilObject::_lookupType($completion_by_id);
                        if ($type == "crsr") {
                            $completion_by = ilContainerReference::_lookupTitle($completion_by_id);
                        } else {
                            $completion_by = ilObject::_lookupTitle($completion_by_id);
                        }
                    }
                } else {
                    $completion_by = '';
                    if ($progress->isSuccessful()) {
                        $names = $node->getNamesOfCompletedOrAccreditedChildren($ass_id);
                        $completion_by = implode(", ", $names);
                    }
                }

                $programme = ilObjStudyProgramme::getInstanceByObjId($progress->getNodeId());
                $plan[] = array( "status" => $progress->getStatus()
                               , "status_repr" => $programme->statusToRepr($progress->getStatus())
                               , "title" => $node->getTitle()
                               , "points_current" => $progress->getCurrentAmountOfPoints()
                               , "points_required" => $progress->getAmountOfPoints()
                               , "possible" => $progress->isSuccessful()
                                    || $programme->canBeCompleted($progress)
                                    || !$progress->isRelevant()
                               , "changed_by" => ilObjUser::_lookupLogin($progress->getLastChangeBy())
                               , "completion_by" => $completion_by
                               , "progress_id" => $progress->getId()
                                //draft/active/outdated
                               , "program_status" => $programme->getStatus()
                               , "assignment_date" => $progress->getAssignmentDate()->format('d.m.Y')
                               , "deadline" => $progress->getDeadline()
                               , "completion_date" => $progress->getCompletionDate() ? $progress->getCompletionDate()->format('d.m.Y') : ''
                               );
            },
            true
        );
        return $plan;
    }

    protected function getManualStatusSelect($a_progress_id, $a_status)
    {
        $parent = $this->getParentObject();
        $options = [
            $parent::MANUAL_STATUS_NONE => '-',
            ilStudyProgrammeProgress::STATUS_IN_PROGRESS => $this->lng->txt("prg_status_in_progress"),
            ilStudyProgrammeProgress::STATUS_ACCREDITED => $this->lng->txt("prg_status_accredited"),
            ilStudyProgrammeProgress::STATUS_NOT_RELEVANT => $this->lng->txt("prg_status_not_relevant")
            //COMPLETED/FAILED are not to be set manually.
        ];

        $allowed = ilStudyProgrammeProgress::getAllowedTargetStatusFor($a_status);

        $options = array_filter(
            $options,
            function ($o) use ($allowed, $parent) {
                return in_array($o, $allowed) || $o === $parent::MANUAL_STATUS_NONE;
            },
            ARRAY_FILTER_USE_KEY
        );

        $select = new ilSelectInputGUI("", $parent::POST_VAR_STATUS . "[$a_progress_id]");
        $select->setOptions($options);
        $select->setValue($parent::MANUAL_STATUS_NONE);

        return $select->render();
    }

    protected function getRequiredPointsInput($a_progress_id, $a_status, $a_points_required)
    {
        if ($a_status != ilStudyProgrammeProgress::STATUS_IN_PROGRESS) {
            return $a_points_required;
        }

        $parent = $this->getParentObject();
        $input = new ilNumberInputGUI("", $parent::POST_VAR_REQUIRED_POINTS . "[$a_progress_id]");
        $input->setValue($a_points_required);
        $input->setSize(5);
        return $input->render();
    }

    protected function getDeadlineInput($a_progress_id, $deadline)
    {
        $parent = $this->getParentObject();
        $gui = new ilDateTimeInputGUI("", $parent::POST_VAR_DEADLINE . "[$a_progress_id]");
        $gui->setDate($deadline ? new ilDateTime($deadline->format('Y-m-d H:i:s'), IL_CAL_DATETIME) : null);
        return $gui->render();
    }
}
