<?php

require_once "Services/ADT/classes/Bridges/class.ilADTSearchBridgeSingle.php";

class ilADTEnumSearchBridgeSingle extends ilADTSearchBridgeSingle
{
    const ENUM_SEARCH_COLUMN = 'value_index';

    protected function isValidADTDefinition(ilADTDefinition $a_adt_def)
    {
        return ($a_adt_def instanceof ilADTEnumDefinition);
    }
    
    
    // table2gui / filter
    
    public function loadFilter()
    {
        $value = $this->readFilter();
        if ($value !== null) {
            $this->getADT()->setSelection($value);
        }
    }

    public function getSearchColumn() : string
    {
        return self::ENUM_SEARCH_COLUMN;
    }

    // form
    
    public function addToForm()
    {
        global $DIC;

        $lng = $DIC['lng'];
        
        $def = $this->getADT()->getCopyOfDefinition();
        
        $options = $def->getOptions();
        asort($options); // ?
        
        $lng->loadLanguageModule("search");
        $options = array("" => $lng->txt("search_any")) + $options;
        
        $select = new ilSelectInputGUI($this->getTitle(), $this->getElementId());
        $select->setOptions($options);
        
        $select->setValue($this->getADT()->getSelection());
        
        $this->addToParentElement($select);
    }
    
    public function importFromPost(array $a_post = null)
    {
        $post = $this->extractPostValues($a_post);
        if (
            is_numeric($post) &&
            $this->shouldBeImportedFromPost($post)
        ) {
            if ($this->getForm() instanceof ilPropertyFormGUI) {
                $item = $this->getForm()->getItemByPostVar($this->getElementId());
                $item->setValue($post);
            } elseif (array_key_exists($this->getElementId(), $this->table_filter_fields)) {
                $this->table_filter_fields[$this->getElementId()]->setValue($post);
                $this->writeFilter($post);
            }
            
            $this->getADT()->setSelection($post);
        } else {
            $this->writeFilter();
            $this->getADT()->setSelection();
        }
    }
    
    
    // db
    
    public function getSQLCondition($a_element_id)
    {
        global $DIC;

        $ilDB = $DIC['ilDB'];

        $search_column = $this->getSearchColumn();
        if (!$this->isNull() && $this->isValid()) {
            return $search_column . ' = ' . $ilDB->quote($this->getADT()->getSelection(), ilDBConstants::T_TEXT);
        }
        return '';
    }
    
    public function isInCondition(ilADT $a_adt)
    {
        assert($a_adt instanceof ilADTEnum);
        
        return $this->getADT()->equals($a_adt);
    }
    
    
    //  import/export
        
    public function getSerializedValue()
    {
        if (!$this->isNull() && $this->isValid()) {
            return serialize(array($this->getADT()->getSelection()));
        }
    }
    
    public function setSerializedValue($a_value)
    {
        $a_value = unserialize($a_value);
        if (is_array($a_value)) {
            $this->getADT()->setSelection($a_value[0]);
        }
    }
}
