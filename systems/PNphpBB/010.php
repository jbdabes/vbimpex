<?php if (!defined('IDIR')) { die; }
/*======================================================================*\
|| ####################################################################
|| # vBulletin Impex
|| # ----------------------------------------------------------------
|| # All PHP code in this file is Copyright 2000-2014 vBulletin Solutions Inc.
|| # This code is made available under the Modified BSD License -- see license.txt
|| # http://www.vbulletin.com 
|| ####################################################################
\*======================================================================*/
/**
* PNphpBB_010 Import Attachment module
*
* @package			ImpEx.PNphpBB
*
*/
class PNphpBB_010 extends PNphpBB_000
{
	var $_version 		= '0.0.1';
	var $_dependent 	= '008';
	var $_modulestring 	= 'Import Attachments';


	function PNphpBB_010()
	{
		// Constructor
	}


	function init(&$sessionobject, &$displayobject, &$Db_target, &$Db_source)
	{
		if ($this->check_order($sessionobject,$this->_dependent))
		{
			if ($this->_restart)
			{
				if ($this->restart($sessionobject, $displayobject, $Db_target, $Db_source,'clear_imported_attachments'))
				{
					$displayobject->display_now('<h4>Imported attachments have been cleared</h4>');
					$this->_restart = true;
				}
				else
				{
					$sessionobject->add_error('fatal',
											 $this->_modulestring,
											 get_class($this) . '::restart failed , clear_imported_attachments','Check database permissions');
				}
			}


			// Start up the table
			$displayobject->update_basic('title','Import Attachment');
			$displayobject->update_html($displayobject->do_form_header('index',substr(get_class($this) , -3)));
			$displayobject->update_html($displayobject->make_hidden_code(substr(get_class($this) , -3),'WORKING'));
			$displayobject->update_html($displayobject->make_hidden_code('import_attachment','working'));
			$displayobject->update_html($displayobject->make_table_header($this->_modulestring));


			// Ask some questions
			$displayobject->update_html($displayobject->make_input_code('Attachments to import per cycle (must be greater than 1)','attachmentperpage',50));
			$displayobject->update_html($displayobject->make_input_code('Full Path to PNphpBB uploads folder.','attachmentsfolder',$sessionobject->get_session_var('attachmentsfolder'),1,60));

			// End the table
			$displayobject->update_html($displayobject->do_form_footer('Continue','Reset'));


			// Reset/Setup counters for this
			$sessionobject->add_session_var(substr(get_class($this) , -3) . '_objects_done', '0');
			$sessionobject->add_session_var(substr(get_class($this) , -3) . '_objects_failed', '0');
			$sessionobject->add_session_var('attachmentstartat','0');
			$sessionobject->add_session_var('attachmentdone','0');
		}
		else
		{
			// Dependant has not been run
			$displayobject->update_html($displayobject->do_form_header('index',''));
			$displayobject->update_html($displayobject->make_description('<p>This module is dependent on <i><b>' . $sessionobject->get_module_title($this->_dependent) . '</b></i> cannot run until that is complete.'));
			$displayobject->update_html($displayobject->do_form_footer('Continue',''));
			$sessionobject->set_session_var(substr(get_class($this) , -3),'FALSE');
			$sessionobject->set_session_var('module','000');
		}
	}


	function resume(&$sessionobject, &$displayobject, &$Db_target, &$Db_source)
	{
		// Set up working variables.
		$displayobject->update_basic('displaymodules','FALSE');
		$target_database_type	= $sessionobject->get_session_var('targetdatabasetype');
		$target_table_prefix	= $sessionobject->get_session_var('targettableprefix');
		$source_database_type	= $sessionobject->get_session_var('sourcedatabasetype');
		$source_table_prefix	= $sessionobject->get_session_var('sourcetableprefix');


		// Per page vars
		$attachment_start_at	= $sessionobject->get_session_var('attachmentstartat');
		$attachment_per_page	= $sessionobject->get_session_var('attachmentperpage');
		$class_num				= substr(get_class($this) , -3);


		// Start the timing
		if(!$sessionobject->get_session_var($class_num . '_start'))
		{
			$sessionobject->timing($class_num ,'start' ,$sessionobject->get_session_var('autosubmit'));
		}


		// Get an array of attachment details
		$attachment_array 	= $this->get_PNphpBB_attachment_details($Db_source, $source_database_type, $source_table_prefix, $attachment_start_at, $attachment_per_page);


		$user_ids_array = $this->get_user_ids($Db_target, $target_database_type, $target_table_prefix, $do_int_val = false);


		// Display count and pass time
		$displayobject->display_now('<h4>Importing ' . count($attachment_array) . ' attachments</h4><p><b>From</b> : ' . $attachment_start_at . ' ::  <b>To</b> : ' . ($attachment_start_at + count($attachment_array)) . '</p>');


		$attachment_object = new ImpExData($Db_target, $sessionobject, 'attachment');


		foreach ($attachment_array as $attachment_id => $attachment_details)
		{
			$try = (phpversion() < '5' ? $attachment_object : clone($attachment_object));
			$dir = $sessionobject->get_session_var('attachmentsfolder');

			if(!is_file($dir . '/' . $attachment_details['filename']))
			{
				$displayobject->display_now('<br /><b>Source file not found </b> :: attachment -> ' . $attachment_details['filename']);
				$sessionobject->set_session_var($class_num . '_objects_failed',$sessionobject->get_session_var($class_num. '_objects_failed') + 1 );
				continue;
			}

			$file = $this->vb_file_get_contents( $dir . '/' . $attachment_details['filename']);

			// Mandatory
			$try->set_value('mandatory', 'filename',				addslashes($attachment_details['filename']));
			$try->set_value('mandatory', 'filedata',				$file);
			$try->set_value('mandatory', 'importattachmentid',		$attachment_id);


			// Non Mandatory
			$try->set_value('nonmandatory', 'userid',				$user_ids_array["$attachment_details[userid]"]);
			$try->set_value('nonmandatory', 'dateline',				$attachment_details['filetime']);
			$try->set_value('nonmandatory', 'visible',				'1');
			$try->set_value('nonmandatory', 'counter',				$attachment_details['downloads']);
			#$try->set_value('nonmandatory', 'filesize',				$attachment_details['filesize']);
			$try->set_value('nonmandatory', 'filesize',				filesize($dir . '/' . $attachment_details['filename']));
			#echo "<h1>'" . $this->get_vb_post_id($Db_target, $target_database_type, $target_table_prefix, $attachment_details['attach_pid']) . "'</h1>";die;

			#$try->set_value('nonmandatory', 'postid',				$this->get_vb_post_id($Db_target, $target_database_type, $target_table_prefix, $attachment_details['attach_pid']));
			$try->set_value('nonmandatory', 'postid',				$attachment_details['postid']);

			$try->set_value('nonmandatory', 'filehash',				md5($file));
			#$try->set_value('nonmandatory', 'posthash',			$attachment_details['posthash']);
			#$try->set_value('nonmandatory', 'thumbnail',			$attachment_details['thumbnail']);
			#$try->set_value('nonmandatory', 'thumbnail_dateline',	$attachment_details['thumbnail_dateline']);


			// Check if attachment object is valid
			if($try->is_valid())
			{
				if($try->import_attachment($Db_target, $target_database_type, $target_table_prefix))
				{
					$displayobject->display_now('<br /><span class="isucc"><b>' . $try->how_complete() . '%</b></span> :: attachment -> ' . $attachment_details['filename']);
					$sessionobject->add_session_var($class_num . '_objects_done',intval($sessionobject->get_session_var($class_num . '_objects_done')) + 1 );
				}
				else
				{
					$sessionobject->set_session_var($class_num . '_objects_failed',$sessionobject->get_session_var($class_num. '_objects_failed') + 1 );
					$sessionobject->add_error('warning', $this->_modulestring, get_class($this) . '::import_custom_profile_pic failed.', 'Check database permissions and database table');
					$displayobject->display_now("<br />Found  attachment and <b>DID NOT</b> imported to the  {$target_database_type} database possibly the origional post is missing");
				}
			}
			else
			{
				$displayobject->display_now("<br />Invalid attachment object, skipping." . $try->_failedon);
			}
			unset($try);
		}// End foreach

		// Check for page end
		if (count($attachment_array) == 0 OR count($attachment_array) < $attachment_per_page)
		{
			$sessionobject->timing($class_num,'stop', $sessionobject->get_session_var('autosubmit'));
			$sessionobject->remove_session_var($class_num . '_start');


			$displayobject->update_html($displayobject->module_finished($this->_modulestring,
										$sessionobject->return_stats($class_num, '_time_taken'),
										$sessionobject->return_stats($class_num, '_objects_done'),
										$sessionobject->return_stats($class_num, '_objects_failed')
										));

			$sessionobject->set_session_var($class_num ,'FINISHED');
			$sessionobject->set_session_var('import_attachment','done');
			$sessionobject->set_session_var('module','000');
			$sessionobject->set_session_var('autosubmit','0');
			$displayobject->update_html($displayobject->print_redirect('index.php','1'));
		}

		$sessionobject->set_session_var('attachmentstartat',$attachment_start_at+$attachment_per_page);
		$displayobject->update_html($displayobject->print_redirect('index.php'));
	}// End resume
}//End Class
# Autogenerated on : September 16, 2004, 12:14 pm
# By ImpEx-generator 1.0.
/*======================================================================*/
?>
