<?php

/*
 * Plugin to handle 'check selected folders for new messages' function in Roundcube
 *
 * @version 1.0
 * @author Sandor Takacs <taki@alkoholista.hu>
 */

class rc_check_recent extends rcube_plugin
{
    public $task = 'mail|settings';

    private $rc;

    function init()
    {
        $this->rc = rcube::get_instance();

        if ($this->rc->task == 'mail') {
            $this->add_hook('check_recent', array($this, 'check_recent_hook'));
        }

        if ($this->rc->task == 'settings') {
            $this->add_hook('preferences_list', array($this, 'preferences_list_hook'));
            $this->add_hook('preferences_save', array($this, 'preferences_save_hook'));
            $this->add_hook('folder_form', array($this, 'folder_form_hook'));
            $this->add_hook('folder_update', array($this, 'folder_update_hook'));
        }

        $this->add_texts('localization', true);
    }

    function check_recent_hook($args)
    {
        $folders       = $args['folders'];
        $check_folders = $this->rc->config->get('check_folders', 'all');
        switch ($check_folders) {
            case 'selected':
                $tmp_recent = $this->rc->config->get('check_recent_folders', array());
                $folders    = array_keys($tmp_recent, true);
                if (!array_key_exists('INBOX', array_flip($folders))) {
                    $folders[] = 'INBOX';
                }
                break;
            case 'inbox':
                if (!array_key_exists('INBOX', array_flip($folders))) {
                    $folders[] = 'INBOX';
                }
                break;
            case 'all':
                break;
        }

        $args['folders'] = $folders;
        return $args;
    }

    function preferences_list_hook($args)
    {
        if ($args['section'] == 'mailbox') {
            $check_value  = $this->rc->config->get('check_folders', 0);
            $check_new    = $args['blocks']['new_message']['options']['check_all_folders'];
            $check_select = new html_select(array('name' => '_check_folders', 'id' => 'rcmfd_check_folders', 'multiple' => false));
            $check_select->add($this->gettext('check_folder_inbox'), 'inbox');
            $check_select->add($this->gettext('check_folder_selected'), 'selected');
            $check_select->add($this->gettext('check_folder_all'), 'all');
            $check_new['content'] = $check_select->show($check_value);
            $check_new['title']   = preg_replace('|rcmfd_check_all_folders|', 'rcmfd_check_folders', $check_new['title']);
            $check_new['title']   = preg_replace('|">.*<|', '">' . $this->gettext('check_folder_title') . "<", $check_new['title']);

            $args['blocks']['new_message']['options']['check_all_folders'] = $check_new;
        }

        return $args;
    }

    function preferences_save_hook($args)
    {
        if ($args['section'] == 'mailbox') {
            $args['prefs']['check_folders'] = isset($_POST['_check_folders']) ? $_POST['_check_folders'] : 'inbox';
            if ($args['prefs']['check_folders'] == 'all') {
                $args['prefs']['check_all_folders'] = true;
            } else {
                $args['prefs']['check_all_folders'] = false;
            }
        }

        return $args;
    }

    function folder_form_hook($args)
    {
        $content     = $args['form']['props']['fieldsets']['settings']['content'];
        $options     = $args['options'];
        $mbox        = $options['name'];
        $checked_all = $this->rc->config->get('check_recent_folders');
        $disabled    = $this->rc->config->get('check_all_folders') ? 1 : ($mbox == 'INBOX' ? 1 : 0);
        $checked     = $checked_all[$mbox] || $disabled ? 1 : 0;

        if (is_array($content) && !array_key_exists('check_recent', $content)) {
            $check_box = new html_checkbox(array('name' => '_check_recent', 'id' => '_check_recent', 'value' => 1));

            $content['check_recent'] = array(
            'label' => $this->gettext('checkmail'),
            'value'	=> $check_box->show($checked, array('disabled' => $disabled)),
            );
        }

        if (is_array($options) && !array_key_exists('check_recent', $options)) {
            $options['check_recent'] = $checked;
        }

        $args['form']['props']['fieldsets']['settings']['content'] = $content;

        $args['options'] = $options;
        return $args;
    }

    function folder_update_hook($args)
    {
        $mbox         = $args['record']['name'];
        $settings     = $args['record']['settings'];
        $check_recent = rcube_utils::get_input_value('_check_recent', rcube_utils::INPUT_POST);
        $tmp_recent   = $this->rc->config->get('check_recent_folders');

        if (!is_array($tmp_recent)) {
            $tmp_recent = array();
        }

        if ($check_recent == 1) {
            $tmp_recent[$mbox] = true;
        } else {
            if (array_key_exists($mbox, $tmp_recent)) {
                unset($tmp_recent[$mbox]);
            }
        }

        $this->rc->user->save_prefs(array('check_recent_folders' => $tmp_recent));

        return $args;
    }

}
