<?php
namespace Vanderbilt\RightToLeftExternalModule;

use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;

class RightToLeftExternalModule extends AbstractExternalModule
{
	function redcap_every_page_top($project_id)
	{
        if (isset($project_id) && $this->getProjectSetting('eventgrid') == true) {
            $redcapSplitVersion = explode(".", REDCAP_VERSION);
            # Optional label class on data entry forms in REDCap that is version dependent
            if ($redcapSplitVersion[0] >= 6 || ($redcapSplitVersion[0] == 6 && $redcapSplitVersion[1] >= 13)) {
                define("LABEL_CSS", "labelrc");
            } else {
                define("LABEL_CSS", "label");
            }
            define("LABEL_MATRIX", "labelmatrix");
            define("MATRIX_CLASS", "mtxfld");
            define("QUESTIONNUM_CLASS", "questionnum");
            echo "<style type='text/css'>";
            echo ".".LABEL_CSS." {
                text-align: right;
            }";
            echo ".".LABEL_MATRIX." {
                text-align: right;
            }";
            echo "</style>";

            ###########################################################################################################
            #The event grids in REDCap have one of two IDs: event_grid_table, record_status_table. These are the only #
            #two things that have these IDs. The code functions to invert the event table so that the last event for  #
            #the project appears first, descending down to the first event and the header column with event names.    #
            ###########################################################################################################
            echo "<script type='text/javascript'>\n";
            echo "$(document).ready(function() {
                $('#event_grid_table thead tr').each(function() {
                    var thLength = $(this).children('th').length;
                    while (thLength > 0) {
                        $(this).find('th:nth-child('+thLength+')').appendTo(this);
                        thLength--;
                    }
                });
                
                $('#event_grid_table tbody tr').each(function() {
                    var tdLength = $(this).children('td').length
                    while (tdLength > 0) {
                        $(this).find('td:nth-child('+tdLength+')').appendTo(this);
                        tdLength--;
                    }
                });
                $('#record_status_table thead tr').each(function() {
                    var th1Length = $(this).children('th').length;
                    while (th1Length > 0) {
                        $(this).find('th:nth-child('+th1Length+')').appendTo(this);
                        th1Length--;
                    }
                });
                $('#record_status_table tbody tr').each(function() {
                    var td1Length = $(this).children('td').length;
                    while (td1Length > 0) {
                        $(this).find('td:nth-child('+td1Length+')').appendTo(this);
                        td1Length--;
                    }
                });
                $('.rsd-left').each(function() {
                	this.style.setProperty('border','none','important');
                	$(this).css('border-right', '2px #777 solid');
                });
                //Scroll the window all the way to the right in the case of overly large event tables.
                window.scrollTo(document.body.scrollWidth,0);
            });";
            echo "</script>";
        }
	}

    function redcap_survey_page($project_id,$record,$instrument) {
        if (isset($project_id) && $this->getProjectSetting('surveyform') == true) {
            $this->drawInputForm($project_id, $record, $instrument);
        }
    }

    function redcap_data_entry_form($project_id,$record,$instrument) {
        if (isset($project_id) && $this->getProjectSetting('dataform') == true) {
            $this->drawInputForm($project_id, $record, $instrument);
        }
    }

    private function drawInputForm($project_id,$record,$instrument) {
        ## This file is meant to be included as a REDCap hook from the hooks/data_entry_form.php or hooks/survey_form.php page
        define('PHONE_FIELD', $this->getProjectSetting('phonefield'));

        //include_once(dirname(dirname(dirname(__FILE__))) . "/plugins/Core/bootstrap.php");
        $projectId = $project_id;
        $recordId = $record;

        //global $Core;

        //$Core->Libraries(array("Project", "Metadata", "Record"));

        $project = new \Project($projectId);
        //$record = new \Plugin\Record($project,[[$project->getFirstFieldName()]],[$project->getFirstFieldName() => $recordId]);

        $metadataList = $project->metadata;

        $redcapSplitVersion = explode(".",REDCAP_VERSION);

        # Optional label class on data entry forms in REDCap that is version dependent
        if ($redcapSplitVersion[0] >= 6 || ($redcapSplitVersion[0] == 6 && $redcapSplitVersion[1] >= 13)) {
            define("LABEL_CSS","labelrc");
        }
        else {
            define("LABEL_CSS","label");
        }
        define("LABEL_MATRIX", "labelmatrix");
        define("CHOICE_MATRIX", "choicematrix");

        echo "<script type='text/javascript'>\n";
        if (defined('PHONE_FIELD') && PHONE_FIELD != "") {
            ## Print javascript to add onBlur functions to validate the listed fields
            ##
            echo "function phoneFieldChange(input) {
                var text = $(input).val();
                text = text.replace(/[(-) \\-+\\.]/g, '');
                var countryCode = '".($this->getProjectSetting('countrycode') != "" ? $this->getProjectSetting('countrycode') : '')."';
                var validText = true;

                if(text == '') return;

                if($.isNumeric(text)) {
                    if(text.length == 12) {
                        if(countryCode != '' && text.substring(0,3) != countryCode) {
                            validText = false;
                        }
                    }
                    else {
                        validText = false;
                    }
                }
                else {
                    validText = false;
                }

                if(!validText) {
                    alert(\"Please enter a valid 12-digit cell phone number, including area code (for example, (\"+".($this->getProjectSetting('countrycode') != "" ? "countryCode" : "XXX")."+\") XXX-XXXX)\");
                    setTimeout(function () {
                        $(input).select();
                    },200);
                }
                else {
                    $(input).val(text);
                }
            }\n\n";
        }

        echo "\$(document).ready(function() {\n";
        foreach($metadataList as $metadata) {
            # Do not want to consider any fields not actually on the form currently being viewed
            if ($metadata['form_name'] != $instrument) {
                continue;
            }
            $fieldName = $metadata['field_name'];
            $annotation = $metadata['misc'];
            $label = preg_replace("/\s+/","",strip_tags($metadata['element_label']));
            $numberMatches = array();
            preg_match("/(^[0-9\.]+)/",$label,$numberMatches);
            //$splitReg = preg_split("/(^[0-9\.]+)/",$label);

            # Any fields marked as phone fields need to have the function to validate their phone format
            if (defined('PHONE_FIELD') && PHONE_FIELD != "") {
                if (strpos($annotation, PHONE_FIELD) !== false) {
                    echo "$('input[name=\"$fieldName\"]').blur(function() { return phoneFieldChange(this); });";
                }
            }
            # Make all data fields read right-to-left
            if ($this->getProjectSetting('textfields') && $metadata['element_preceding_header'] != "") {
                echo "$('#$fieldName-sh-tr td').css('direction','rtl');";
                echo "$('#$fieldName-sh-tr td').css('text-align','right');";
                if ($metadata['grid_name'] != "") {
                    echo "$('#".$metadata['grid_name']."-mtxhdr-tr').css('direction','rtl');
                    $('#".$metadata['grid_name']."-mtxhdr-tr').find('td.questionnum').each(function() {
                        $(this).appendTo($(this).parent());
                    });";
                }
            }
            # Flip all data elements on the page to conform to a right-to-left format
            if ($this->getProjectSetting('formlayout')) {
                if ($metadata['element_type'] == "slider") {
                    echo "$('#$fieldName-tr td.sldrnumtd').each(function() {
                        $(this).appendTo($(this).parent());
                    });";
                } elseif ($metadata['grid_name'] != "") {
                    echo "$('#$fieldName-tr').css('direction','rtl');
                        $('#$fieldName-tr').find('td.questionnummatrix').each(function() {
                            $(this).appendTo($(this).parent());
                        });";
                } elseif ($metadata['element_type'] == 'descriptive') {
                    echo "$('#$fieldName-tr').css('direction','rtl');
                        $('#$fieldName-tr').find('td.questionnum').each(function() {
                            $(this).appendTo($(this).parent());
                        });";
                }
                else {
                    echo "$('#$fieldName-tr td').each(function() {
                        if (!$(this).hasClass('questionnum')) {
                            $(this).parent().prepend($(this));
                        }
                    });";
                    if ($metadata['element_type'] == "radio" || $metadata['element_type'] == "checkbox") {
                        echo "$('#$fieldName-tr').find('input').each(function() {
                            $(this).appendTo($(this).parent());
                        });";
                    }
                }
            }
            //echo "\$('#$fieldName-tr td').css('text-align','right');";
            //echo "\$('#$fieldName-tr td:first').appendTo('#$fieldName-tr');";
        }
        echo "});";

        echo "$('tr').each(function() {";
        # For each 'tr' element on the page, make the interior elements right-to-left.
        if ($this->getProjectSetting('textfields')) {
            echo "$(this).find('td." . LABEL_CSS . "').css('direction','rtl');
                $(this).css('text-align','right');
                $(this).find('input').each(function() {
                    $(this).css({'direction' : 'rtl', 'margin-left' : '3px'});
                });
                $(this).find('textarea').each(function() {
                    $(this).css({'direction' : 'rtl', 'margin-left' : '3px'});
                });";
        }
        # For each 'tr' element on the page, rearrange the interior page elements to match a right-to-left formatting.
        /*if ($this->getProjectSetting('formlayout')) {
            echo "$(this).find('td." . LABEL_CSS . ", td.".LABEL_MATRIX."').appendTo(this);";
        }*/
        echo "});";
        if ($this->getProjectSetting('formlayout')) {
            //echo "$('td.".LABEL_MATRIX." table tr td').each(function() { $(this).appendTo($(this).parent()); });";
            echo "$('tr.".MATRIX_CLASS." div.resetLinkParent').css('text-align','left');";
        }
        echo "</script>";
    }
}
