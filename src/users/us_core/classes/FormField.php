<?php
/*
 * Class inheritance related to Form Fields:
 * The rule with UserSpice is "Do NOT modify files under us_core/ - make the change
 * under local/ instead." For these Form Fields the way to do that is to use the
 * classes defined in local/Classes/FormFieldTypes.php and to make modifications only
 * to that file and local/Classes/FormField.php.
 *
 * us_core/Classes/FormField.php (this) defines class "US_FormField" which is just
 * the parent class. This class is abstract. Do not modify this file.
 *
 * local/Classes/FormField.php in turn defines class "FormField" which inherits from
 * class "US_FormField". This class is abstract. Feel free to make changes to
 * local/Classes/FormField.php.
 *
 * us_core/Classes/FormFieldTypes.php in turn defines several classes such as
 * "US_FormField_Text", "US_FormField_Button", "US_FormField_Hidden", etc. which
 * inherit from class "FormField". These are abstract. Do not modify this file.
 *
 * local/Classes/FormFieldTypes.php in turn defines several classes such as
 * "FormField_Text", "FormField_Button", "FormField_Hidden", etc. which inherit
 * from the classes mentioned above which are named the same but with a "US_" prefix.
 * THESE ARE THE CLASSES YOU SHOULD USE IN YOUR CODE TO DEFINE FORM FIELDS!
 * Feel free to modify local/Classes/FormFieldTypes.php.
 */
abstract class US_FormField extends Element {
    protected $_validateObject=null,
        $_fieldName=null,
        $_dbFieldName=null,
        $_fieldId='',
        $_fieldLabel='',
        $_fieldPlaceholder=null,
        $_fieldValue=null,
        $_fieldNewValue=null,
        $_fieldType=null, // should be set by inheriting classes
        $_deleteMe=false,
        $_isDBField=true; // whether this is a field in the DB
    public
        $HTML_Pre =
            '<div class="{DIV_CLASS}">
             <label class="{LABEL_CLASS}" for="{FIELD_ID}">{LABEL_TEXT}
             <span class="{HINT_CLASS}" title="{HINT_TEXT}"></span></label>
             <br />',
        $HTML_Input =
            '<input class="{INPUT_CLASS}" type="{TYPE}" id="{FIELD_ID}" '
            .'name="{FIELD_NAME}" placeholder="{PLACEHOLDER}" value="{VALUE}" '
            .'{REQUIRED_ATTRIB} {EXTRA_ATTRIB}>',
        $HTML_Post =
            '<br />
             </div> <!-- {DIV_CLASS} -->',
        $HTML_Script = '',
        $elementList = ['Pre', 'Input', 'Post'];
    # Commented-out values below are added just-in-time prior to replacement
    # (see self::getHTML()) Note that some replacement macros may be set/used
    # in self::setRepeatValues() as well.
    public
        $MACRO_Div_Class = 'form-group',
        $MACRO_Label_Class = 'control-label',
        $MACRO_Label_Text = '',
        $MACRO_Input_Class = 'form-control',
        $MACRO_Required_Class = 'fa fa-asterisk',
        $MACRO_Hint_Class = '',
        $MACRO_Hint_Class_Not_Required = 'fa fa-info-circle',
        $MACRO_Hint_Class_Required = 'fa fa-asterisk',
        $MACRO_TH_Class = '',
        $MACRO_Placeholder = '',
        $MACRO_Extra_Attrib = '',
        $MACRO_Value = '';

    public function __construct($opts=[]) {
        global $T;
        if ($fn = @$opts['dbfield']) {
            $db = DB::getInstance();
            $field_def = $db->query("SELECT * FROM $T[field_defs] WHERE name = ?", [$fn])->first(true);
            $dbFieldnm = $fn;
            if ($field_def) {
                $fn = $field_def['alias'];
            }
            $this->setDBFieldName($dbFieldnm);
        } else {
            $fn = @$opts['field']; // grab it if it's there
            $field_def = []; // no field-def to work with
        }
        if ($fn) {
            $this->setFieldName($fn);
        }
        if (is_null($this->getPlaceholder())) {
            $this->setPlaceholder($this->getFieldLabel());
        }
        parent::__construct($opts);
        # Now handle what we found in $field_def, but don't let
        # values there override what was passed in $opts
        $this->handleOpts(array_diff_key((array)$field_def, $opts));
    }

    public function handle1Opt($name, $val) {
        switch(strtolower($name)) {
            case 'display_lang':
                $val = lang($val);
                # NOTE: No break - falling through to 'display' with $val set
            case 'display':
                # NOTE: We could be falling through from above with no break
                $this->setFieldLabel($val);
                $this->setMacro('Label_Text', $val);
                return true;
                break;
            case 'value':
                $this->setFieldValue($val);
                return true;
                break;
            case 'new_valid':
            case 'new_validate':
                $args = [$this->_dbFieldName => $val];
                $val = new Validate($args);
                # NOTE: No break - falling through to 'valid' with $val set
            case 'valid':
            case 'validate':
                # NOTE: We could be falling through from above with no break
                $this->setValidator($val);
                return true;
                break;
            case 'keep_if':
            case 'keepif':
                $val = !$val;
                # NOTE: No break - falling through to 'deleteif'
            case 'delete_if':
            case 'deleteif':
                # NOTE: We could be falling through from above with no break
                $this->setDeleteMe($val);
                return true;
                break;
            case 'is_dbfield':
            case 'is_db_field':
            case 'isdbfield':
                $this->setIsDBField($val);
                return true;
                break;
            case 'placeholder':
                $this->setPlaceholder($val);
                return true;
                break;
            case 'extra':
                $this->setMacro('Extra_Attrib', $val);
                return true;
                break;
        }
        if (parent::handle1Opt($name, $val)) {
            return true;
        }
    }

    public function getMacros($s, $opts) {
        $this->MACRO_Type = $this->getFieldType();
        $this->MACRO_Field_Name = $this->getFieldName();
        $this->MACRO_Field_ID = $this->getFieldId();
        $this->MACRO_Label_Text = $this->getFieldLabel();
        $this->MACRO_Placeholder = $this->getPlaceholder();
        $this->MACRO_Value = $this->getFieldValue();
        $this->MACRO_Required_Attrib = ($this->getRequired() ? 'required' : '');
        $this->MACRO_Hint_Class = $this->getHintClass();
        return parent::getMacros($s, $opts);
    }
    # $opts is a hash which can have the following indexed values:
    #  'replaces' => ['{search}'=>'replace',...]
    public function xgetHTML($opts=[]) {
        # Start by calculating $this->HTMLInput.
        $html = $this->getHTMLElements($opts);
        # Now we will calculate an array of macros for search/replace.
        # Static values are already in $this->_macros but others have
        # to be set "just in time"...
        $justInTimeRepl = [
                    '{TYPE}'           => $this->getFieldType(),
                    '{FIELD_NAME}'     => $this->getFieldName(),
                    '{FIELD_ID}'       => $this->getFieldId(),
                    '{LABEL_TEXT}'     => $this->getFieldLabel(),
                    '{PLACEHOLDER}'    => $this->getPlaceholder(),
                    '{VALUE}'          => $this->getFieldValue(),
                    '{REQUIRED_ATTRIB}'=> ($this->getRequired() ? 'required' : ''),
                    '{HINT_CLASS}'     => $this->getHintClass(),
        ];
        $this->jitMacros($justInTimeRepl);
        $repl = array_merge($this->_macros, $justInTimeRepl, (array)@$opts['replaces']);
        # since this is slightly "expensive" we won't evaluate unless it is needed
        if (!isset($repl['{HINT_TEXT}']) && $this->getValidator()) {
            $repl['{HINT_TEXT}'] = $this->getValidator()->describe($this->_fieldName);
        }
        $html = str_replace(array_keys($repl), array_values($repl), $html);
        return $html;
    }
    public function getHTMLElements($opts) {
        return $this->HTMLPre . $this->HTMLInput . $this->HTMLPost;
    }
    // these are overall just-in-time replacement macros
    public function jitMacros(&$macros) {
        // don't do anything by default - each field type may
        // have something to do...
    }

    public function describeValidation() {
        return $this->getValidator()->describe($this->_fieldName);
    }
    public function getHintClass() {
        if ($this->getRequired()) {
            return $this->MACRO_Hint_Class_Required;
        } else {
            return $this->MACRO_Hint_Class_Not_Required;
        }
    }

    public function isChanged() {
        return ($this->_fieldNewValue == $this->_fieldValue);
    }
	public function getPlaceholder(){
		return $this->_fieldPlaceholder;
	}
	public function setPlaceholder($placeholder){
		$this->_fieldPlaceholder = $placeholder;
	}
    public function setReplace($search, $replace) {
        $this->_macros[$search] = $replace;
    }
    # Does the validation for this field say it is a required field?
	public function getRequired() {
        if ($valid = $this->getValidator()) {
            return $valid->getRequired($this->getFieldName());
        } else {
            return false;
        }
	}
	public function setRequired($v){
        if ($valid = $this->getValidator()) {
            $valid->setRequired($this->getFieldName(), $v);
        } else {
            throw new Exception("No validation. Cannot set `required` for field {$this->_fieldName}.");
        }
	}
    # <input ... id="THIS-IS-FIELD-ID" ...>
    public function setFieldId($id) {
        $this->_fieldId = $id;
    }
    public function getFieldId() {
        # Often developers will not specify the ID since they will just
        # want it to be the same as the field name. Thus the short-cut.
        if (!empty($this->_fieldId)) {
            return $this->_fieldId;
        } else {
            return $this->getFieldName();
        }
    }
    public function setFieldLabel($label) {
        $this->_fieldLabel = $label;
    }
    public function getFieldLabel() {
        return $this->_fieldLabel;
    }
    public function setIsDBField($isdb) {
        $this->_isDBField = $isdb;
    }
    public function getIsDBField() {
        return $this->_isDBField;
    }
    public function setDBFieldName($fn) {
        $this->_dbFieldName = $fn;
    }
    public function getDBFieldName() {
        return $this->_dbFieldName;
    }
    public function setFieldName($fn) {
        $this->_fieldName = $fn;
    }
    public function getFieldName() {
        return $this->_fieldName;
    }
    public function setFieldType($type) {
        $this->_fieldType = $type;
    }
    public function getFieldType() {
        return $this->_fieldType;
    }
    public function setFieldValue($value) {
        $this->_fieldValue = $value;
    }
    public function getFieldValue() {
        return $this->_fieldValue;
    }
    public function getNewValue() {
        return $this->_fieldNewValue;
    }
    public function setNewValue($val) {
        $this->_fieldNewValue = Input::sanitize($val);
    }

    # methods related to validation
    public function setValidator($v) {
        $this->_validateObject = $v;
    }
    public function hasValidation() {
        return (boolean)$this->_validateObject;
    }
    public function getValidator() {
        return $this->_validateObject;
    }

    #
    # these methods are simply "pass-through" to the validate object
    #
    public function dataIsValid($data) {
        if ($this->hasValidation()) {
            if (!$data) {
                $data = $this->_fieldNewValue;
            }
            return $this->getValidator()->check($data)->passed();
        } else {
            return true; // if no validation then it cannot fail
        }
    }
    public function stackErrorMessages($errors) {
        if ($this->hasValidation()) {
            return $this->getValidator()->stackErrorMessages($errors);
        } else {
            return $errors;
        }
    }

    public function getHTMLScripts() {
        return $this->HTML_Script;
    }
    public function deleteMe() {
        return $this->getDeleteMe();
    }
    public function getDeleteMe() {
        return $this->_deleteMe;
    }
    public function setDeleteMe($val) {
        $this->_deleteMe = $val;
    }
    // if an inheriting class needs to adjust the snippets
    // they can do it by setting any of ...
    // ... setting HTMLPre, HTMLInput, HTMLPost directly
    // ... or by overriding this function to do something else
    public function fixSnippets() {
    }
}
