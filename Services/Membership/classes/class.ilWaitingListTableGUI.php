<?php
/*
        +-----------------------------------------------------------------------------+
        | ILIAS open source                                                           |
        +-----------------------------------------------------------------------------+
        | Copyright (c) 1998-2006 ILIAS open source, University of Cologne            |
        |                                                                             |
        | This program is free software; you can redistribute it and/or               |
        | modify it under the terms of the GNU General Public License                 |
        | as published by the Free Software Foundation; either version 2              |
        | of the License, or (at your option) any later version.                      |
        |                                                                             |
        | This program is distributed in the hope that it will be useful,             |
        | but WITHOUT ANY WARRANTY; without even the implied warranty of              |
        | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the               |
        | GNU General Public License for more details.                                |
        |                                                                             |
        | You should have received a copy of the GNU General Public License           |
        | along with this program; if not, write to the Free Software                 |
        | Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA. |
        +-----------------------------------------------------------------------------+
*/

include_once('./Services/Table/classes/class.ilTable2GUI.php');


/**
* GUI class for course/group waiting list
*
* @author Stefan Meyer <smeyer.ilias@gmx.de>
* @version $Id$
*
* @ingroup ServicesMembership
*/
class ilWaitingListTableGUI extends ilTable2GUI
{
    protected $rep_object = null;
    
    protected $waiting_list = null;
    protected $wait = array();
    protected $wait_user_ids = array();

    protected static $all_columns = null;
    protected static $has_odf_definitions = false;


    /**
     * ilWaitingListTableGUI constructor.
     * @param $a_parent_obj
     * @param \ilObject $rep_object
     * @param \ilWaitingList $waiting_list
     */
    public function __construct($a_parent_obj, ilObject $rep_object, $waiting_list)
    {
        global $DIC;

        $lng = $DIC['lng'];
        $ilCtrl = $DIC['ilCtrl'];
        
        $this->lng = $lng;
        $this->lng->loadLanguageModule('grp');
        $this->lng->loadLanguageModule('crs');
        $this->lng->loadLanguageModule('sess');
        $this->lng->loadLanguageModule('ps');
        $this->ctrl = $ilCtrl;
        
        $this->rep_object = $rep_object;

        $this->setExternalSorting(false);
        $this->setExternalSegmentation(true);
        $this->setId('crs_wait_' . $this->getRepositoryObject()->getId());
        $this->setFormName('waiting');
        $this->setPrefix('waiting');

        parent::__construct($a_parent_obj, 'participants');

        $this->setFormAction($this->ctrl->getFormAction($a_parent_obj, 'participants'));

        $this->addColumn('', 'f', "1", true);
        $this->addColumn($this->lng->txt('name'), 'lastname', '20%');
        
        $all_cols = $this->getSelectableColumns();
        foreach ($this->getSelectedColumns() as $col) {
            $this->addColumn($all_cols[$col]['txt'], $col);
        }

        $this->addColumn($this->lng->txt('application_date'), 'sub_time', "10%");
        $this->addColumn('', 'mail', '10%');
        
        $this->addMultiCommand('confirmAssignFromWaitingList', $this->lng->txt('assign'));
        $this->addMultiCommand('confirmRefuseFromList', $this->lng->txt('refuse'));
        $this->addMultiCommand('sendMailToSelectedUsers', $this->lng->txt('crs_mem_send_mail'));

        $this->setDefaultOrderField('sub_time');
        
        // begin-patch clipboard
        $this->lng->loadLanguageModule('user');
        $this->addMultiCommand('addToClipboard', $this->lng->txt('clipboard_add_btn'));
        // end-patch clipboard

        $this->setPrefix('waiting');
        $this->setSelectAllCheckbox('waiting', true);
        
        $this->setRowTemplate("tpl.show_waiting_list_row.html", "Services/Membership");
        
        $this->enable('sort');
        $this->enable('header');
        $this->enable('numinfo');
        $this->enable('select_all');

        $this->waiting_list = $waiting_list;
        
        include_once('Modules/Course/classes/Export/class.ilCourseDefinedFieldDefinition.php');
        self::$has_odf_definitions = ilCourseDefinedFieldDefinition::_hasFields($this->getRepositoryObject()->getId());
    }
    
    /**
     * @return \ilWaitingList
     */
    protected function getWaitingList()
    {
        return $this->waiting_list;
    }
    
    /**
     * Get repository object
     * @return ilObject
     */
    protected function getRepositoryObject()
    {
        return $this->rep_object;
    }
    
    /**
     * Set user ids
     * @param int[] $a_user_ids
     */
    public function setUserIds($a_user_ids)
    {
        $this->wait_user_ids = $this->wait = [];
        foreach ($a_user_ids as $usr_id) {
            $this->wait_user_ids[] = $usr_id;
            $this->wait[$usr_id] = $this->getWaitingList()->getUser($usr_id);
        }
    }

    /**
     * configure numeric ordering
     * @param string $a_field
     * @return bool
     */
    public function numericOrdering($a_field)
    {
        switch ($a_field) {
            case 'sub_time':
                return true;
        }
        return parent::numericOrdering($a_field);
    }

    /**
     * Get selectable columns
     * @return
     */
    public function getSelectableColumns()
    {
        if (self::$all_columns) {
            return self::$all_columns;
        }

        include_once './Services/PrivacySecurity/classes/class.ilExportFieldsInfo.php';
        $ef = ilExportFieldsInfo::_getInstanceByType($this->getRepositoryObject()->getType());
        self::$all_columns = $ef->getSelectableFieldsInfo($this->getRepositoryObject()->getId());

        // #25215
        if (
            is_array(self::$all_columns) &&
            array_key_exists('consultation_hour', self::$all_columns)
        ) {
            unset(self::$all_columns['consultation_hour']);
        }

        if (
            !is_array(self::$all_columns) ||
            !array_key_exists('login', self::$all_columns)
        ) {
            self::$all_columns['login'] = [
                'default' => 1,
                'txt' => $this->lng->txt('login')
            ];
        }
        return self::$all_columns;
    }
    
    
    /**
     * fill row
     *
     * @access public
     * @param
     * @return
     */
    public function fillRow($a_set)
    {
        global $DIC;

        $ilUser = $DIC['ilUser'];
        
        include_once('./Services/Calendar/classes/class.ilDateTime.php');
        include_once './Modules/Course/classes/class.ilObjCourseGrouping.php';
        
        if (!ilObjCourseGrouping::_checkGroupingDependencies($this->getRepositoryObject(), $a_set['usr_id']) and
            ($ids = ilObjCourseGrouping::getAssignedObjects())) {
            $prefix = $this->getRepositoryObject()->getType();
            $this->tpl->setVariable(
                'ALERT_MSG',
                sprintf(
                    $this->lng->txt($prefix . '_lim_assigned'),
                    ilObject::_lookupTitle(current($ids))
                )
            );
        }
                
        $this->tpl->setVariable('VAL_ID', $a_set['usr_id']);
        $this->tpl->setVariable('VAL_NAME', $a_set['lastname'] . ', ' . $a_set['firstname']);

        foreach ($this->getSelectedColumns() as $field) {
            switch ($field) {
                case 'gender':
                    $a_set['gender'] = $a_set['gender'] ? $this->lng->txt('gender_' . $a_set['gender']) : '';
                    $this->tpl->setCurrentBlock('custom_fields');
                    $this->tpl->setVariable('VAL_CUST', $a_set[$field]);
                    $this->tpl->parseCurrentBlock();
                    break;

                case 'birthday':
                    $a_set['birthday'] = $a_set['birthday'] ? ilDatePresentation::formatDate(new ilDate($a_set['birthday'], IL_CAL_DATE)) : $this->lng->txt('no_date');
                    $this->tpl->setCurrentBlock('custom_fields');
                    $this->tpl->setVariable('VAL_CUST', $a_set[$field]);
                    $this->tpl->parseCurrentBlock();
                    break;
                
                case 'odf_last_update':
                    $this->tpl->setVariable('VAL_CUST', (string) $a_set['odf_info_txt']);
                    break;
                
                case 'org_units':
                    $this->tpl->setCurrentBlock('custom_fields');
                    include_once './Modules/OrgUnit/classes/PathStorage/class.ilOrgUnitPathStorage.php';
                    $this->tpl->setVariable('VAL_CUST', (string) ilOrgUnitPathStorage::getTextRepresentationOfUsersOrgUnits($a_set['usr_id']));
                    $this->tpl->parseCurrentBlock();
                    break;
                

                default:
                    $this->tpl->setCurrentBlock('custom_fields');
                    $this->tpl->setVariable('VAL_CUST', isset($a_set[$field]) ? (string) $a_set[$field] : '');
                    $this->tpl->parseCurrentBlock();
                    break;
            }
        }
        
        $this->tpl->setVariable('VAL_SUBTIME', ilDatePresentation::formatDate(new ilDateTime($a_set['sub_time'], IL_CAL_UNIX)));
        
        #$this->tpl->setVariable('VAL_LOGIN',$a_set['login']);
        
        $this->showActionLinks($a_set);
    }
    
    /**
     * read data
     *
     * @access protected
     * @param
     * @return
     */
    public function readUserData()
    {
        $this->determineOffsetAndOrder();

        include_once './Services/User/classes/class.ilUserQuery.php';

        $additional_fields = $this->getSelectedColumns();
        unset($additional_fields["firstname"]);
        unset($additional_fields["lastname"]);
        unset($additional_fields["last_login"]);
        unset($additional_fields["access_until"]);
        unset($additional_fields['org_units']);

        $udf_ids = $usr_data_fields = $odf_ids = array();
        foreach ($additional_fields as $field) {
            if (substr($field, 0, 3) == 'udf') {
                $udf_ids[] = substr($field, 4);
                continue;
            }
            if (substr($field, 0, 3) == 'odf') {
                $odf_ids[] = substr($field, 4);
                continue;
            }

            $usr_data_fields[] = $field;
        }

        $l = $this->getLimit();
        $usr_data = ilUserQuery::getUserListData(
            $this->getOrderField(),
            $this->getOrderDirection(),
            $this->getOffset(),
            $this->getLimit(),
            '',
            '',
            null,
            false,
            false,
            0,
            0,
            null,
            $usr_data_fields,
            $this->wait_user_ids
        );
        if (0 === count($usr_data['set']) && $this->getOffset() > 0 && $this->getExternalSegmentation()) {
            $this->resetOffset();

            $usr_data = ilUserQuery::getUserListData(
                $this->getOrderField(),
                $this->getOrderDirection(),
                $this->getOffset(),
                $this->getLimit(),
                '',
                '',
                null,
                false,
                false,
                0,
                0,
                null,
                $usr_data_fields,
                $this->wait_user_ids
            );
        }

        ilLoggerFactory::getLogger('mem')->dump($this->wait_user_ids);
        ilLoggerFactory::getLogger('mem')->dump($usr_data);
        
        foreach ((array) $usr_data['set'] as $user) {
            $usr_ids[] = $user['usr_id'];
        }

        // merge course data
        $course_user_data = $this->getParentObject()->readMemberData($usr_ids, array());
        $a_user_data = array();
        foreach ((array) $usr_data['set'] as $ud) {
            $a_user_data[$ud['usr_id']] = array_merge($ud, $course_user_data[$ud['usr_id']]);
        }

        // Custom user data fields
        if ($udf_ids) {
            include_once './Services/User/classes/class.ilUserDefinedData.php';
            $data = ilUserDefinedData::lookupData($usr_ids, $udf_ids);
            foreach ($data as $usr_id => $fields) {
                if (!$this->checkAcceptance($usr_id)) {
                    continue;
                }

                foreach ($fields as $field_id => $value) {
                    $a_user_data[$usr_id]['udf_' . $field_id] = $value;
                }
            }
        }
        // Object specific user data fields
        if ($odf_ids) {
            include_once './Modules/Course/classes/Export/class.ilCourseUserData.php';
            $data = ilCourseUserData::_getValuesByObjId($this->getRepositoryObject()->getId());
            foreach ($data as $usr_id => $fields) {
                // #7264: as we get data for all course members filter against user data
                if (!$this->checkAcceptance($usr_id) || !in_array($usr_id, $usr_ids)) {
                    continue;
                }

                foreach ($fields as $field_id => $value) {
                    $a_user_data[$usr_id]['odf_' . $field_id] = $value;
                }
            }
            
            // add last edit date
            include_once './Services/Membership/classes/class.ilObjectCustomUserFieldHistory.php';
            foreach (ilObjectCustomUserFieldHistory::lookupEntriesByObjectId($this->getRepositoryObject()->getId()) as $usr_id => $edit_info) {
                if (!isset($a_user_data[$usr_id])) {
                    continue;
                }
                
                include_once './Services/PrivacySecurity/classes/class.ilPrivacySettings.php';
                if ($usr_id == $edit_info['update_user']) {
                    $a_user_data[$usr_id]['odf_last_update'] = '';
                    $a_user_data[$usr_id]['odf_info_txt'] = $GLOBALS['DIC']['lng']->txt('cdf_edited_by_self');
                    if (ilPrivacySettings::_getInstance()->enabledAccessTimesByType($this->getRepositoryObject()->getType())) {
                        $a_user_data[$usr_id]['odf_last_update'] .= ('_' . $edit_info['editing_time']->get(IL_CAL_UNIX));
                        $a_user_data[$usr_id]['odf_info_txt'] .= (', ' . ilDatePresentation::formatDate($edit_info['editing_time']));
                    }
                } else {
                    $a_user_data[$usr_id]['odf_last_update'] = $edit_info['update_user'];
                    $a_user_data[$usr_id]['odf_last_update'] .= ('_' . $edit_info['editing_time']->get(IL_CAL_UNIX));
                    
                    $name = ilObjUser::_lookupName($edit_info['update_user']);
                    $a_user_data[$usr_id]['odf_info_txt'] = ($name['firstname'] . ' ' . $name['lastname'] . ', ' . ilDatePresentation::formatDate($edit_info['editing_time']));
                }
            }
        }

        foreach ($usr_data['set'] as $user) {
            // Check acceptance
            if (!$this->checkAcceptance($user['usr_id'])) {
                continue;
            }
            // DONE: accepted
            foreach ($usr_data_fields as $field) {
                $a_user_data[$user['usr_id']][$field] = $user[$field] ? $user[$field] : '';
            }
        }
        
        // Waiting list subscription
        foreach ($this->wait as $usr_id => $wait_usr_data) {
            if (isset($a_user_data[$usr_id])) {
                $a_user_data[$usr_id]['sub_time'] = $wait_usr_data['time'];
            }
        }
        
        $this->setMaxCount($usr_data['cnt'] ? $usr_data['cnt'] : 0);
        return $this->setData($a_user_data);
    }
    
    /**
     * Show action links (mail ; edit crs|grp data)
     * @param type $a_set
     */
    public function showActionLinks($a_set)
    {
        if (!self::$has_odf_definitions) {
            $this->ctrl->setParameterByClass(get_class($this->getParentObject()), 'member_id', $a_set['usr_id']);
            $link = $this->ctrl->getLinkTargetByClass(get_class($this->getParentObject()), 'sendMailToSelectedUsers');
            $this->tpl->setVariable('MAIL_LINK', $link);
            $this->tpl->setVariable('MAIL_TITLE', $this->lng->txt('crs_mem_send_mail'));
            $this->ctrl->setParameterByClass(get_class($this->getParentObject()), 'member_id', null);
            return true;
        }
        
        // show action menu
        include_once './Services/UIComponent/AdvancedSelectionList/classes/class.ilAdvancedSelectionListGUI.php';
        $list = new ilAdvancedSelectionListGUI();
        $list->setSelectionHeaderClass('small');
        $list->setItemLinkClass('small');
        $list->setId('actl_' . $a_set['usr_id'] . '_' . $this->getId());
        $list->setListTitle($this->lng->txt('actions'));

        $this->ctrl->setParameterByClass(get_class($this->getParentObject()), 'member_id', $a_set['usr_id']);
        $this->ctrl->setParameter($this->parent_obj, 'member_id', $a_set['usr_id']);
        $trans = $this->lng->txt($this->getRepositoryObject()->getType() . '_mem_send_mail');
        $link = $this->ctrl->getLinkTargetByClass(get_class($this->getParentObject()), 'sendMailToSelectedUsers');
        $list->addItem($trans, '', $link, 'sendMailToSelectedUsers');
        
        $this->ctrl->setParameterByClass('ilobjectcustomuserfieldsgui', 'member_id', $a_set['usr_id']);
        $trans = $this->lng->txt($this->getRepositoryObject()->getType() . '_cdf_edit_member');
        $list->addItem($trans, '', $this->ctrl->getLinkTargetByClass('ilobjectcustomuserfieldsgui', 'editMember'));
        $this->ctrl->setParameterByClass(get_class($this->getParentObject()), 'member_id', null);
        
        $this->tpl->setVariable('ACTION_USER', $list->getHTML());
    }
    
    
    
    protected function checkAcceptance()
    {
        return true;
    }
}
