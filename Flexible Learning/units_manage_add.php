<?php
/*
Gibbon, Flexible & Open School System
Copyright (C) 2010, Ross Parker

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

use Gibbon\Forms\Form;
use Gibbon\Module\FlexibleLearning\Forms\FlexibleLearningFormFactory;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

  if (isActionAccessible($guid, $connection2, '/modules/Flexible Learning/units_manage_add.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Get action with highest precendence
    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if ($highestAction == false) {
        $page->addError(__('The highest grouped action cannot be determined.'));
    } else {
        $gibbonDepartmentID = $_GET['gibbonDepartmentID'] ?? '';
        $name = $_GET['name'] ?? '';
        $view = $_GET['view'] ?? '';

        //Proceed!
        $urlParams = compact('gibbonDepartmentID', 'name', 'view');

        $page->breadcrumbs
             ->add(__m('Manage Units'), 'units_manage.php', $urlParams)
             ->add(__m('Add Unit'));

        $returns = array();
        $editLink = '';
        if (isset($_GET['editID'])) {
            $editLink = $gibbon->session->get('absoluteURL').'/index.php?q=/modules/Flexible Learning/units_manage_edit.php&flexibleLearningUnitID='.$_GET['editID'].'&gibbonDepartmentID='.$_GET['gibbonDepartmentID'].'&name='.$_GET['name'];
        }
        if (isset($_GET['return'])) {
            returnProcess($guid, $_GET['return'], $editLink, $returns);
        }

        if ($gibbonDepartmentID != '' or $name != '') {
            echo "<div class='linkTop'>";
            echo "<a href='".$gibbon->session->get('absoluteURL')."/index.php?q=/modules/Flexible Learning/units_manage.php&gibbonDepartmentID=$gibbonDepartmentID&name=$name&view=$view'>".__($guid, 'Back to Search Results').'</a>';
            echo '</div>';
        }

        $form = Form::create('action', $gibbon->session->get('absoluteURL').'/modules/'.$gibbon->session->get('module')."/units_manage_addProcess.php?gibbonDepartmentID=$gibbonDepartmentID&name=$name&view=$view");
        $form->setFactory(FlexibleLearningFormFactory::create($pdo));

        $form->addHiddenValue('address', $gibbon->session->get('address'));


        // UNIT BASICS
        $form->addRow()->addHeading(__m('Unit Basics'));

        $row = $form->addRow();
            $row->addLabel('name', __('Name'));
            $row->addTextField('name')->maxLength(40)->required();

        $sql = "SELECT flexibleLearningCategoryID AS value, name FROM flexibleLearningCategory WHERE active='Y' ORDER BY name";
        $row = $form->addRow();
            $row->addLabel('flexibleLearningCategoryID', __('Category'));
            $row->addSelect('flexibleLearningCategoryID')->fromQuery($pdo, $sql, [])->required()->placeholder();

        $row = $form->addRow();
            $row->addLabel('blurb', __('Blurb'));
            $row->addTextArea('blurb')->required();

        $licences = array(
            "Copyright" => __("Copyright"),
            "Creative Commons BY" => __("Creative Commons BY"),
            "Creative Commons BY-SA" => __("Creative Commons BY-SA"),
            "Creative Commons BY-SA-NC" => __("Creative Commons BY-SA-NC"),
            "Public Domain" => __("Public Domain")
        );
        $row = $form->addRow()->addClass('advanced');
            $row->addLabel('license', __('License'))->description(__('Under what conditions can this work be reused?'));
            $row->addSelect('license')->fromArray($licences)->placeholder();

        $row = $form->addRow();
            $row->addLabel('file', __m('Logo'))->description(__m('125px x 125px'));
            $row->addFileUpload('file')->accepts('.jpg,.jpeg,.gif,.png');

        $row = $form->addRow();
            $row->addLabel('active', __('Active'));
            $row->addYesNo('active')->required();

        //MAJORS AND MINORS
        $sql = "SELECT major1, major2, minor1, minor2 FROM flexibleLearningUnit WHERE active='Y'";
        $result = $pdo->executeQuery(array(), $sql);
        $options = array();
        while ($option=$result->fetch()){
          $options[]=$option['major1'];
          $options[]=$option['major2'];
          $options[]=$option['minor1'];
          $options[]=$option['minor2'];
        }
        $form->addRow()->addHeading(__m('Majors and Minors'));
        $row = $form->addRow();
            $row->addLabel('major1', __('Major 1'));
            $row->addTextField('major1')->autocomplete($options)->required();
        $row = $form->addRow();
            $row->addLabel('major2', __('Major 2'));
            $row->addTextField('major2')->autocomplete($options);
        $row = $form->addRow();
            $row->addLabel('minor1', __('Minor 1'));
            $row->addTextField('minor1')->autocomplete($options);
        $row = $form->addRow();
            $row->addLabel('minor2', __('Minor 2'));
            $row->addTextField('minor2')->autocomplete($options);


        // UNIT OUTLINE
        $form->addRow()->addHeading(__m('Unit Outline'))->append(__m('The contents of this field are viewable to all users, SO AVOID CONFIDENTIAL OR SENSITIVE DATA!'));

        $unitOutline = getSettingByScope($connection2, 'Free Learning', 'unitOutlineTemplate');
        $row = $form->addRow();
            $column = $row->addColumn();
            $column->addLabel('outline', __('Unit Outline'));
            $column->addEditor('outline', $guid)->setRows(30)->showMedia()->setValue($unitOutline);


        // SMART BLOCKS
        $form->addRow()->addHeading(__m('Smart Blocks'))->append(__m('Smart Blocks aid unit planning by giving teachers help in creating and maintaining new units, splitting material into smaller chunks. As well as predefined fields to fill, Smart Blocks provide a visual view of the content blocks that make up a unit. Blocks may be any kind of content, such as discussion, assessments, group work, outcome etc.'));

        $blockCreator = $form->getFactory()
            ->createButton('addNewBlock')
            ->setValue(__('Click to create a new block'))
            ->addClass('advanced addBlock');

        $row = $form->addRow()->addClass('advanced');
            $customBlocks = $row->addFlexibleLearningSmartBlocks('smart', $gibbon->session, $guid)
                ->addToolInput($blockCreator);

        for ($i=0 ; $i<5 ; $i++) {
            $customBlocks->addBlock("block$i");
        }

        $form->addHiddenValue('blockCount', "5");

        $row = $form->addRow();
            $row->addFooter();
            $row->addSubmit();

        echo $form->getOutput();
    }
}
?>