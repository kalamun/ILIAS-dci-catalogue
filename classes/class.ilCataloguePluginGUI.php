<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

/**
 * Test Page Component GUI
 * @author            Roberto Pasini <bonjour@kalamun.net>
 * @ilCtrl_isCalledBy ilCataloguePluginGUI: ilPCPluggedGUI
 * @ilCtrl_isCalledBy ilCataloguePluginGUI: ilUIPluginRouterGUI
 */
class ilCataloguePluginGUI extends ilPageComponentPluginGUI
{
    protected /* ilLanguage */ $lng;
    protected ilCtrl $ctrl;
    protected ilGlobalTemplateInterface $tpl;
    protected ilTree $tree;
    protected ilObjectService $object;
    protected ilObjUser $user;
    protected dciCourse $dciCourse;

    public function __construct()
    {
        global $DIC;

        parent::__construct();

        $this->lng = $DIC->language();
        $this->ctrl = $DIC->ctrl();
        $this->tpl = $DIC['tpl'];
        $this->tree = $DIC->repositoryTree();
        $this->object = $DIC->object();
        $this->user = $DIC['ilUser'];

        $this->dciCourse = new dciCourse();

        //require_once('./Services/Calendar/classes/class.ilDateTime.php');
    }

    /**
     * Execute command
     */
    public function executeCommand(): void
    {
        $next_class = $this->ctrl->getNextClass();

        switch ($next_class) {
            default:
                // perform valid commands
                $cmd = $this->ctrl->getCmd();
                if (in_array($cmd, array("create", "save", "edit", "update", "cancel"))) {
                    $this->$cmd();
                }
                break;
        }
    }

    /**
     * Create
     */
    public function insert(): void
    {
        $form = $this->initForm(true);
        $this->tpl->setContent($form->getHTML());
    }

    /**
     * Save new pc example element
     */
    public function create(): void
    {
        $form = $this->initForm(true);
        if ($this->saveForm($form, true)) {
            $this->tpl->setOnScreenMessage("success", $this->lng->txt("msg_obj_modified"), true);
            $this->returnToParent();
        }
        $form->setValuesByPost();
        $this->tpl->setContent($form->getHTML());
    }

    public function edit(): void
    {
        $form = $this->initForm();

        $this->tpl->setContent($form->getHTML());
    }

    public function update(): void
    {
        $form = $this->initForm(false);
        if ($this->saveForm($form, false)) {
            $this->tpl->setOnScreenMessage("success", $this->lng->txt("msg_obj_modified"), true);
            $this->returnToParent();
        }
        $form->setValuesByPost();
        $this->tpl->setContent($form->getHTML());
    }


    protected function getRoles()
    {
        global $DIC;
        $rbacreview = $DIC['rbacreview'];
        $ilUser = $DIC['ilUser'];
        
        include_once './Services/AccessControl/classes/class.ilObjRole.php';
        
        $type = ilRbacReview::FILTER_ALL;
        $filter = '';

        $role_list = $rbacreview->getRolesByFilter($type, 0, '');
        
        $rows = [];
        foreach ((array) $role_list as $role) {
            if (
                $role['parent'] and
                    (
                        $GLOBALS['DIC']['tree']->isDeleted($role['parent']) or
                        !$GLOBALS['DIC']['tree']->isInTree($role['parent'])
                    )
            ) {
                continue;
            }

            $title = ilObjRole::_getTranslation($role['title']);
            $rows[$role['obj_id']] = $title;
        }

        return $rows;
    }


    public static function getCourses(): array {
        global $DIC;
        $db = $DIC->database();

        $query = "SELECT obj_id FROM crs_settings";
        $res = $db->query($query);
        $courses = [];
        while ($entry = $res->fetch(ilDBConstants::FETCHMODE_OBJECT)) {
            $course_id = $entry->obj_id;
            $obj = ilObjectFactory::getInstanceByObjId($course_id);
            $course_title = $obj->getTitle();
            $courses[$course_id] = $course_title;
        }

        return $courses;
    }



    /**
     * Init editing form
     */
    protected function initForm(bool $a_create = false): ilPropertyFormGUI
    {
        $form = new ilPropertyFormGUI();


        // title
        $input_title = new ilTextInputGUI($this->lng->txt("title"), 'title');
        $input_title->setMaxLength(255);
        $input_title->setSize(40);
        $input_title->setRequired(false);
        $form->addItem($input_title);

        // description
        $input_description = new ilTextInputGUI($this->lng->txt("description"), 'description');
        $input_description->setMaxLength(255);
        $input_description->setSize(40);
        $input_description->setRequired(false);
        $form->addItem($input_description);

        
        $role = unserialize($prop['description']);
        if (!$role) {
            $role = [
                "role_id" => [],
                "course_id" => [0, 0, 0, 0, 0],
            ];
        }
        
        $available_courses = self::getCourses();
        $available_roles = self::getRoles();
        
        // role
        $select_role_id = new ilSelectInputGUI($this->plugin->txt("Role"), $class);
        $select_role_id->setPostVar("role_id");
        $select_role_id->setOptions([""] + $available_roles);
        $select_role_id->setRequired(false);
        $form->addItem($select_role_id);

        // courses
        $select_course = [];
        foreach($role['course_id'] as $i => $course_id) {
            $select_course[$i] = new ilSelectInputGUI($this->plugin->txt("Course") . ' ' . ($i+1), $class);
            $select_course[$i]->setPostVar("course_id_" . $i);
            $select_course[$i]->setOptions([""] + $available_courses);
            $select_course[$i]->setRequired(false);
            $form->addItem($select_course[$i]);
        }
            
            // save and cancel commands
        if ($a_create) {
            $this->addCreationButton($form);
            $form->addCommandButton("cancel", $this->lng->txt("cancel"));
            $form->setTitle($this->plugin->getPluginName());
        } else {
            $prop = $this->getProperties();
            $input_title->setValue($prop['title']);
            $input_description->setValue($prop['description']);

            $select_role_id->setValue($prop['role_id']);

            foreach($role['course_id'] as $i => $course_id) {
                $select_course[$i]->setValue($prop['course_id_' . $i]);
            }

            $form->addCommandButton("update", $this->lng->txt("save"));
            $form->addCommandButton("cancel", $this->lng->txt("cancel"));
            $form->setTitle($this->plugin->getPluginName());
        }

        $form->setFormAction($this->ctrl->getFormAction($this));
        return $form;
    }

    protected function saveForm(ilPropertyFormGUI $form, bool $a_create): bool
    {
        if ($form->checkInput()) {
            $properties = $this->getProperties();

            $properties['title'] = $form->getInput('title');
            $properties['description'] = $form->getInput('description');
            $properties['role_id'] = $form->getInput('role_id');
            $properties['course_id_0'] = $form->getInput('course_id_0');
            $properties['course_id_1'] = $form->getInput('course_id_1');
            $properties['course_id_2'] = $form->getInput('course_id_2');
            $properties['course_id_3'] = $form->getInput('course_id_3');
            $properties['course_id_4'] = $form->getInput('course_id_4');

            if ($a_create) {
                return $this->createElement($properties);
            } else {
                return $this->updateElement($properties);
            }
        }

        return false;
    }

    /**
     * Cancel
     */
    public function cancel()
    {
        $this->returnToParent();
    }

    /**
     * Get HTML for element
     * @param string    page mode (edit, presentation, print, preview, offline)
     * @return string   html code
     */
    public function getElementHTML( /* string */$a_mode, /* array */ $a_properties, /* string */ $a_plugin_version) /* : string */
    {
        global $DIC;
        $ctrl = $DIC->ctrl();
        $db = $DIC->database();
        
        $title = !empty($a_properties['title']) ? $a_properties['title'] : "";
        $description = !empty($a_properties['description']) ? $a_properties['description'] : "";

        ob_start();

        $rbacreview = $DIC['rbacreview'];
        $user_id = $DIC['ilUser']->id;
        $user_roles = $rbacreview->assignedRoles($user_id);

        if (in_array($a_properties['role_id'], $user_roles) || in_array(2, $user_roles) /* always display for admins */) {
            ?>
            <div class="kalamun-catalogue">
                <div class="kalamun-catalogue_body">
                    <div class="kalamun-catalogue_title">
                        <h2><?= $title; ?></h2>
                        <?php
                        if (!empty($description)) {?>
                            <div class="kalamun-catalogue_description"><?= $description; ?></div>
                        <?php }
                        ?>
                    </div>
                    <div class="kalamun-catalogue_courses">
                        <?php
                        $courses = [];
                        if (!empty($a_properties['course_id_0'])) $courses[] = $a_properties['course_id_0'];
                        if (!empty($a_properties['course_id_1'])) $courses[] = $a_properties['course_id_1'];
                        if (!empty($a_properties['course_id_2'])) $courses[] = $a_properties['course_id_2'];
                        if (!empty($a_properties['course_id_3'])) $courses[] = $a_properties['course_id_3'];
                        if (!empty($a_properties['course_id_4'])) $courses[] = $a_properties['course_id_4'];

                        foreach ($courses as $obj_id) {
                            if (intval($obj_id) == 0) continue;

                            $obj = ilObjectFactory::getInstanceByObjId($obj_id);
                            if (empty($obj) || $obj->getOfflineStatus()) {
                                continue;
                            }
                            
                            $mandatory_objects = $this->dciCourse->get_mandatory_objects($obj_id);
                            $completed_objects_count = count(array_filter($mandatory_objects, fn($k) => $k['completed'] ));
    
                            $type = $obj->getType();
                            $title = $obj->getTitle();
                            $description = $obj->getDescription();
                            $tile_image = $this->object->commonSettings()->tileImage()->getByObjId($obj_id);
                            $ctrl->setParameterByClass("ilrepositorygui", "ref_id", $ref_id);
                            $permalink = $ctrl->getLinkTargetByClass("ilrepositorygui", "view");
    
                            $course_tabs = dciSkin_tabs::getCourseTabs($ref_id);
                            $mandatory_cards_count = 0;
                            $completed_cards_count = 0;
                            
                            foreach ($course_tabs as $page) {
                                $mandatory_cards_count += $page['cards_mandatory'];
                                $completed_cards_count += $page['cards_completed'];
                            }
    
                            foreach ($course_tabs as $page) {
                                if (!$page['completed']) {
                                    $permalink = $page['permalink'];
                                    break;
                                }
                            }
    
                            /* progress statuses:
                            0 = attempt
                            1 = in progress;
                            2 = completed;
                            3 = failed;
                            */
                            $lp = ilLearningProgress::_getProgress($this->user->getId(), $obj_id);
                            $lp_status = ilLPStatusCollection::_lookupStatus($obj_id, $this->user->getId());
                            $lp_percent = ilLPStatusCollection::_lookupPercentage($obj_id, $this->user->getId());
                            $lp_in_progress = !empty(ilLPStatusCollection::_lookupInProgressForObject($obj_id, [$this->user->getId()]));
                            $lp_completed = ilLPStatusCollection::_hasUserCompleted($obj_id, $this->user->getId());
                            $lp_failed = !empty(ilLPStatusCollection::_lookupFailedForObject($obj_id, [$this->user->getId()]));
                            $lp_downloaded = $lp['visits'] > 0 && $type == "file";
    
                            $typical_learning_time = ilMDEducational::_getTypicalLearningTimeSeconds($obj_id);
    
                            ?>
                            <div class="kalamun-catalogue_course" data-permalink="<?= $permalink; ?>">
                                <div class="kalamun-catalogue_thumb">
                                    <?= ($tile_image->exists() ? '<a href="' . $permalink . '" title="' . addslashes($title) . '"><img src="' . $tile_image->getFullPath() . '"></a>' : '<span class="empty-thumb"></span>'); ?>
                                </div>
                                <div class="kalamun-catalogue_course_body">
                                    <div class="kalamun-catalogue_heading">
                                        <h3><?= $title; ?></h3>
                                    </div>
                                    <div class="kalamun-catalogue_course_meta">
                                        <p class="kalamun-catalogue_title"><?= $title; ?></p>
                                        <?php
                                        if (!empty($description)) {
                                            ?><p><?= $description; ?></p><?php
                                        }
                                        ?>
                                        <div class="kalamun-catalogue_course_progress">
                                            <?php
                                            if (!empty($typical_learning_time)) {
                                                ?>
                                                <div class="kalamun-catalogue_course_progress_line learning-time">
                                                    <?php
                                                    $time_spent = explode(":", gmdate("H:i", $typical_learning_time));
                                                    echo '<h6>Course estimated learning time' /*. $DIC->language()->txt("time_spent") */ . '</h6>';
                                                    echo '<span><span class="icon-picto_timer"></span></span>';
                                                    echo '<div>';
                                                        if ($time_spent[0] > 0) echo $time_spent[0] . ' hours <br>';
                                                        if ($time_spent[1] > 0) echo $time_spent[1] . ' minutes ';
                                                    echo '</div>';
                                                    ?>
                                                </div>
                                                <?php
                                            }
                                            ?>
                                        </div>
                                        <div class="kalamun-catalogue_course_cta">
                                            <a href="<?= $permalink; ?>"><button><?= $lp['spent_seconds'] > 60 ? 'Continue' : 'Start'; ?> <span class="icon-right"></span></button></a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php
                        }
                        ?>
                        </div>
                    </div>
            </div>
            </div>
            <?php
        }

        $html = ob_get_clean();
        return $html;
    }
}