<?php

/**
 * Questionnaire Assessment Encounters Template
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2022 Jerry Padgett <sjpadgett@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once(__DIR__ . "/../../globals.php");
require_once("$srcdir/user.inc");

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header;
use OpenEMR\Services\QuestionnaireService;

$questionnaire_form = $_GET['questionnaire_form'] ?? null;
// for new questionnaires user must be admin. leave strict conditional.
$is_authorized = AclMain::aclCheckCore('admin', 'forms') ||
    ($questionnaire_form !== 'New Questionnaire' && $_GET['formname'] ?? null === 'questionnaire_assessments');
if (!empty($_GET['id'] ?? 0) && empty($questionnaire_form)) {
    $formid = $_GET['id'];
    $form = formFetch("form_questionnaire_assessments", $formid);
}
$q_json = '';
$lform = '';
if (!empty($questionnaire_form) && $questionnaire_form != 'New Questionnaire') {
    // since we are here then user is authorized for a pre-approved questionnaire form.
    $is_authorized = true;
    $service = new QuestionnaireService();
    $q = $service->fetchEncounterQuestionnaireForm($questionnaire_form);
    $q_json = $q['questionnaire'] ?: '';
    $lform = $q['lform'] ?: '';
    $mode = 'new_form';
}
$do_warning = checkUserSetting('disable_form_disclaimer', '1') === true ? 0 : 1;
?>
<!DOCTYPE html>
<html>
<head>
    <title id="main_title"><?php echo xlt('Questionnaire'); ?></title>
    <?php Header::setupHeader(); ?>
    <link href="<?php echo $GLOBALS['assets_static_relative']; ?>/lforms/webcomponent/styles.css" media="screen" rel="stylesheet" />
    <script>
        function saveQR() {
            top.restoreSession();
            let qr = LForms.Util.getFormFHIRData('QuestionnaireResponse', 'R4');
            let formElement = document.getElementById("formContainer");
            let data = LForms.Util.getUserData(formElement, true, true, true);
            document.getElementById('lform_response').value = JSON.stringify(data);
            document.getElementById('questionnaire_response').value = JSON.stringify(qr);
            if (!document.getElementById('questionnaire').value) {
                let lForm = JSON.parse(document.getElementById('lform').value);
                let qFhir = LForms.Util.getFormFHIRData("Questionnaire", 'R4', data);
                document.getElementById('questionnaire').value = JSON.stringify(qFhir);
            }
            return true;
        }

        function initUpdate() {
            // Merge QuestionnaireResponse
            let qResponse = JSON.parse(document.getElementById('questionnaire_response').value);
            let lForm = JSON.parse(document.getElementById('lform').value);
            let responseData = LForms.Util.mergeFHIRDataIntoLForms("QuestionnaireResponse", qResponse, lForm, "R4");
            $(".isNew").toggleClass('d-none');
            LForms.Util.addFormToPage(responseData, 'formContainer');
        }

        function initNewForm() {
            let lform = <?php echo js_escape($lform); ?>;
            let qFhir = <?php echo js_escape($q_json); ?>;
            let formName = <?php echo js_escape($questionnaire_form); ?>;
            let data;
            if (lform) {
                data = JSON.parse(lform);
            } else if (qFhir) {
                let qData = JSON.parse(qFhir);
                data = LForms.Util.convertFHIRQuestionnaireToLForms(qData, 'R4');
            } else {
                alert(xl('Error Missing Form.'));
                parent.closeTab(window.name, false);
            }
            document.getElementById('questionnaire').value = JSON.stringify(qFhir);
            document.getElementById('lform').value = JSON.stringify(data);
            document.getElementById('form_name').value = jsAttr(formName);
            document.getElementById('code_type').value = jsAttr('LOINC');
            document.getElementById('code').value = jsAttr(data.code);
            if (typeof data.copyrightNotice !== 'undefined' && data.copyrightNotice > '') {
                document.getElementById('copyright').value = jsAttr(data.copyrightNotice);
                document.getElementById('copyrightNotice').innerHTML = jsText(data.copyrightNotice);
            }
            LForms.Util.addFormToPage(data, 'formContainer');
            $(".isNew").toggleClass('d-none');
        }

        function initSearch() {
            <?php if ($do_warning) { ?>
            let msg = xl("OpenEMR is not responsible for any copyrights and permissions pertaining to questionnaires or assessments imported and implemented with this feature.") + "<br />";
            msg += xl("Some, if not many, LOINC forms will display a copyright notice for information regarding permissions.") +
                "<br /><br />";
            msg += xl("Click Got it icon to dismiss this alert forever.");
            // dialog alertMsg 5th parameter will set flag to disable this user seeing message
            alertMsg(msg, 20000, 'danger', '', 'disable_form_disclaimer');
            <?php } ?>
            const ac = new LForms.Def.Autocompleter.Search('loinc_item', 'https://clinicaltables.nlm.nih.gov/api/loinc_items/v3/search?type=form&available=true&df=text,LOINC_NUM', {tableFormat: true, valueCols: [0, 1], colHeaders: ['Text', 'LOINC Code']});

            LForms.Def.Autocompleter.Event.observeListSelections('loinc_item', function () {
                let formCode = ac.getSelectedCodes()[0];
                if (formCode) {
                    top.restoreSession();
                    let url = "https://clinicaltables.nlm.nih.gov/loinc_form_definitions?loinc_num=" + encodeURIComponent(formCode);
                    fetch(url).then((form) => {
                        return form.json();
                    }).then((data) => {
                        let saveButton = document.getElementById('save_response');
                        let registryButton = document.getElementById('save_registry');
                        saveButton.classList.remove("d-none");
                        registryButton.classList.remove("d-none");
                        document.getElementById('lform').value = JSON.stringify(data);
                        document.getElementById('form_name').value = jsAttr(data.name);
                        document.getElementById('code_type').value = jsAttr(data.type);
                        document.getElementById('code').value = jsAttr(data.code);
                        if (typeof data.copyrightNotice !== 'undefined' && data.copyrightNotice > '') {
                            document.getElementById('copyright').value = jsAttr(data.copyrightNotice);
                            document.getElementById('copyrightNotice').innerHTML = jsText(data.copyrightNotice);
                        }
                        LForms.Util.addFormToPage(data, 'formContainer');
                        return data;
                    }).then((data) => {
                        let qFhir = LForms.Util.getFormFHIRData("Questionnaire", 'R4', data);
                        document.getElementById('questionnaire').value = JSON.stringify(qFhir);
                    });
                }
            });
        }
    </script>
</head>
<body>
    <div class="container-xl my-2">
        <?php if (!$is_authorized) { ?>
            <div class="d-flex flex-column w-100 align-items-center">
                <?php
                echo "<h3>" . xlt("Not Authorized") . "</h3>";
                echo "<h4>" . xlt("You must have administrator privileges.") . "</h4>";
                echo "<h5>" . xlt("Contact an administrator to create a new questionnaire.") . "</h5>";
                ?>
                <button type='button' class="btn btn-secondary btn-cancel" onclick="parent.closeTab(window.name, false)"><?php echo xlt('Exit'); ?></button>
            </div>
            <?php die(); } ?>
        <form method="post" id="qa_form" name="qa_form" onsubmit="return saveQR(this)" action="<?php echo $rootdir; ?>/forms/questionnaire_assessments/save.php?form_id=<?php echo attr_url($formid) ?>">
            <input type="hidden" name="csrf_token_form" value="<?php echo attr(CsrfUtils::collectCsrfToken()); ?>" />
            <input type="hidden" id="lform" name="lform" value="<?php echo attr($form['lform'] ?? ''); ?>" />
            <input type="hidden" id="lform_response" name="lform_response" value="<?php echo attr($form['lform_response'] ?? ''); ?>" />
            <input type="hidden" id="code_type" name="code_type" value="<?php echo attr($form['code_type'] ?? ''); ?>" />
            <input type="hidden" id="code" name="code" value="<?php echo attr($form['code'] ?? ''); ?>" />
            <input type="hidden" id="copyright" name="copyright" value="<?php echo attr($form['copyright'] ?? ''); ?>" />
            <input type="hidden" id="questionnaire" name="questionnaire" value="<?php echo attr($form['questionnaire'] ?? ''); ?>" />
            <input type="hidden" id="questionnaire_response" name="questionnaire_response" value="<?php echo attr($form['questionnaire_response'] ?? ''); ?>" />
            <div class="form-group">
                <div class="input-group isNew">
                    <label for="loinc_item" class="font-weight-bold mt-2 mr-1"><?php echo xlt("Search and Select a LOINC form") . ': '; ?></label>
                    <input class="form-control search_field" type="text" id="loinc_item" placeholder="<?php echo xla("Type to search"); ?>" autocomplete="off" role="combobox" aria-expanded="false">
                </div>
                <div>
                    <p class="text-center"><?php echo "<span class='font-weight-bold'>" . xlt("Note") . ": </span><i>" . xlt("LOINC form definitions are subject to the LOINC"); ?>
                        <a href="http://loinc.org/terms-of-use" target="_blank"><?php echo xlt("terms of use.") . "</i>"; ?></php></a></p>
                    <p id="copyrightNotice">
                        <?php echo text($form['copyright'] ?? ''); ?>
                    </p>
                </div>
                <div class="input-group isNew">
                    <hr />
                    <label class="font-weight-bolder" for="form_name"><?php echo xlt("Form Name") . ':'; ?></label>
                    <input required type="text" class="form-control skip-template-editor ml-1" id="form_name" name="form_name" title="<?php echo xla('You may edit name to shorten or be more understandable.'); ?>" placeholder="<?php echo xla('Name of form. Edit or leave as received.'); ?>" value="<?php echo attr($form['form_name']) ?? ''; ?>" />
                </div>
            </div>
            <hr />
            <div id="formContainer"></div>
            <div class="btn-group my-2">
                <button type="submit" class="btn btn-primary btn-save isNew d-none" id="save_response" title="<?php echo xla('Save current form or create a new one time questionnaire for this encounter if this is a New Questionnaire form.'); ?>"><?php echo xlt("Save"); ?></button>
                <button type="submit" class="btn btn-primary btn-save d-none" id="save_registry" name="save_registry" title="<?php echo xla('Register as a new encounter form for reuse in any encounter.'); ?>"><?php echo xlt("or Register Form"); ?></button>
                <button type='button' class="btn btn-secondary btn-cancel" onclick="parent.closeTab(window.name, false)"><?php echo xlt('Cancel'); ?></button>
            </div>
        </form>
    </div>
    <!-- Below scripts must be in body. -->
    <script src="<?php echo $GLOBALS['assets_static_relative']; ?>/lforms/webcomponent/assets/lib/zone.min.js"></script>
    <script src="<?php echo $GLOBALS['assets_static_relative']; ?>/lforms/webcomponent/scripts.js"></script>
    <script src="<?php echo $GLOBALS['assets_static_relative']; ?>/lforms/webcomponent/runtime-es2015.js"></script>
    <script src="<?php echo $GLOBALS['assets_static_relative']; ?>/lforms/webcomponent/polyfills-es2015.js"></script>
    <script src="<?php echo $GLOBALS['assets_static_relative']; ?>/lforms/webcomponent/main-es2015.js"></script>
    <script src="<?php echo $GLOBALS['assets_static_relative']; ?>/lforms/fhir/R4/lformsFHIR.min.js"></script>
    <script>
        let formMode = <?php echo js_escape($mode); ?>;
        <?php if ($mode == 'update') { ?>
        window.onload = initUpdate();
        <?php } elseif ($mode == 'new') { ?>
        window.onload = initSearch();
        <?php } elseif ($mode == 'new_form') { ?>
        window.onload = initNewForm();
        <?php } ?>
    </script>
</body>
</html>
