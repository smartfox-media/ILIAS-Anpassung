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

require_once "classes/class.ilObjUser.php";
require_once "classes/class.ilMailbox.php";
require_once "classes/class.ilMail.php";

/**
* @author Jens Conze
* @version $Id$
*
* @ingroup ServicesMail
* @ilCtrl_Calls ilMailFolderGUI: ilMailAddressbookGUI, ilMailAttachmentGUI, ilMailSearchGUI, ilMailOptionsGUI
*/
class ilMailFolderGUI
{
	private $tpl = null;
	private $ctrl = null;
	private $lng = null;
	
	private $umail = null;
	private $mbox = null;

	private $errorDelete = false;

	public function __construct()
	{
		global $tpl, $ilCtrl, $lng, $ilUser;

		$this->tpl = $tpl;
		$this->ctrl = $ilCtrl;
		$this->lng = $lng;
		
		$this->ctrl->saveParameter($this, "mobj_id");

		$this->umail = new ilMail($ilUser->getId());
		$this->mbox = new ilMailBox($ilUser->getId());

		if ($_POST["mobj_id"] != "")
		{
			$_GET["mobj_id"] = $_POST["mobj_id"];
		}
		// IF THERE IS NO OBJ_ID GIVEN GET THE ID OF MAIL ROOT NODE
		if(!$_GET["mobj_id"])
		{
			$_GET["mobj_id"] = $this->mbox->getInboxFolder();
		}
	}

	public function executeCommand()
	{
		/* User views mail and wants to delete it */
		if ($_GET["action"] == "deleteMails" &&
			$_GET["mail_id"])
		{
			$_GET["cmd"] = "post";
			$_POST["cmd"]["editFolder"] = true;
			$_POST["action"] = "deleteMails";
			$_POST["mail_id"] = array($_GET["mail_id"]);
		}

		$forward_class = $this->ctrl->getNextClass($this);
		switch($forward_class)
		{
			case 'ilmailaddressbookgui':
				include_once 'Services/Mail/classes/class.ilMailAddressbookGUI.php';

				$this->ctrl->forwardCommand(new ilMailAddressbookGUI());
				break;

			case 'ilmailoptionsgui':
				include_once 'Services/Mail/classes/class.ilMailOptionsGUI.php';

				$this->ctrl->forwardCommand(new ilMailOptionsGUI());
				break;

			default:
				if (!($cmd = $this->ctrl->getCmd()))
				{
					$cmd = "showFolder";
				}
				$this->$cmd();
				break;
		}
		return true;
	}

	public function add()
	{
		global $lng, $ilUser;

		if($_GET["mail_id"] != "")
		{
			if (is_array($mail_data = $this->umail->getMail($_GET["mail_id"])))
			{
				require_once "classes/class.ilAddressbook.php";
				$abook = new ilAddressbook($ilUser->getId());

				$tmp_user = new ilObjUser($mail_data["sender_id"]);
				$abook->addEntry($tmp_user->getLogin(),
							$tmp_user->getFirstname(),
							$tmp_user->getLastname(),
							$tmp_user->getEmail());
				ilUtil::sendInfo($lng->txt("mail_entry_added"));

			}
		}
		
		$this->showMail();
		
	}

	public function showFolder()
	{
		global $ilUser;

		$this->tpl->addBlockFile("ADM_CONTENT", "adm_content", "tpl.mail.html", "Services/Mail");
		$this->tpl->setVariable("HEADER", $this->lng->txt("mail"));

		$this->ctrl->setParameter($this, "offset", $_GET["offset"]);
		$this->ctrl->setParameter($this, "cmd", "post");
		$this->tpl->setVariable("ACTION", $this->ctrl->getLinkTarget($this));
		$this->ctrl->clearParameters($this);

		// BEGIN CONFIRM_DELETE
		if($_POST["action"] == "deleteMails" &&
			!$this->errorDelete &&
			$_POST["action"] != "confirm" &&
			$this->mbox->getTrashFolder() == $_GET["mobj_id"])
		{
			$this->tpl->setCurrentBlock("CONFIRM_DELETE");
			$this->tpl->setVariable("BUTTON_CONFIRM",$this->lng->txt("confirm"));
			$this->tpl->setVariable("BUTTON_CANCEL",$this->lng->txt("cancel"));
			$this->tpl->parseCurrentBlock();
		}
		
		// BEGIN MAIL ACTIONS
		$actions = $this->mbox->getActions($_GET["mobj_id"]);
		
		$this->tpl->setCurrentBlock("mailactions");
		foreach($actions as $key => $action)
		{
			if($key == 'moveMails')
			{
				$folders = $this->mbox->getSubFolders();
				foreach($folders as $folder)
				{
					$this->tpl->setVariable("MAILACTION_VALUE", $folder["obj_id"]);
					if($folder["type"] != 'user_folder')
					{
						$this->tpl->setVariable("MAILACTION_NAME",$action." ".$this->lng->txt("mail_".$folder["title"]));
					}
					else
					{
						$this->tpl->setVariable("MAILACTION_NAME",$action." ".$folder["title"]);
					}
					$this->tpl->parseCurrentBlock();
				}
			}
			else
			{
				$this->tpl->setVariable("MAILACTION_NAME", $action);
				$this->tpl->setVariable("MAILACTION_VALUE", $key);
				$this->tpl->setVariable("MAILACTION_SELECTED",$_POST["action"] == 'delete' ? 'selected' : '');
				$this->tpl->parseCurrentBlock();
			}
		}
		// END MAIL ACTIONS
		
		
		// SHOW_FOLDER ONLY IF viewmode is flatview
		if(!isset($_SESSION["viewmode"]) ||
			$_SESSION["viewmode"] == 'flat')
		{
			$this->tpl->setCurrentBlock("show_folder");
			$this->tpl->setCurrentBLock("flat_select");
		   
			foreach($folders as $folder)
			{
				if($folder["obj_id"] == $_GET["mobj_id"])
				{
					$this->tpl->setVariable("FLAT_SELECTED","selected");
				}
				$this->tpl->setVariable("FLAT_VALUE",$folder["obj_id"]);
				if($folder["type"] == 'user_folder')
				{
					$this->tpl->setVariable("FLAT_NAME", $folder["title"]);
				}
				else
				{
					$this->tpl->setVariable("FLAT_NAME", $this->lng->txt("mail_".$folder["title"]));
				}
				$this->tpl->parseCurrentBlock();
			}
			$this->tpl->setVariable("TXT_FOLDERS", $this->lng->txt("mail_change_to_folder"));
			$this->tpl->setVariable("FOLDER_VALUE",$this->lng->txt("submit"));
			$this->tpl->parseCurrentBlock();
		}
		// END SHOW_FOLDER
		$this->ctrl->setParameter($this, "offset", $_GET["offset"]);
		$this->tpl->setVariable("ACTION_FLAT", $this->ctrl->getLinkTarget($this));
		$this->ctrl->clearParameters($this);
		
		// BEGIN MAILS
		$mailData = $this->umail->getMailsOfFolder($_GET["mobj_id"]);
		$mail_count = count($mailData);
		
		// TODO: READ FROM MAIL_OPTIONS
		$mail_max_hits = $ilUser->getPref('hits_per_page');
		$counter = 0;
		foreach ($mailData as $mail)
		{
			if($mail["sender_id"] &&
				!ilObjectFactory::ObjectIdExists($mail["sender_id"]))
			{
				--$mail_count;
				continue;
			}
			// LINKBAR
			if($mail_count > $mail_max_hits)
			{
				$params = array(
					"mobj_id"		=> $_GET["mobj_id"]);
			}
			$start = $_GET["offset"];
			$linkbar = ilUtil::Linkbar($this->ctrl->getLinkTarget($this),$mail_count,$mail_max_hits,$start,$params);
			if ($linkbar)
			{
				$this->tpl->setVariable("LINKBAR", $linkbar);
			}
			if($counter >= ($start+$mail_max_hits))
			{
				break;
			}
			if($counter < $start)
			{
				++$counter;
				continue;
			}
		
			// END LINKBAR
			++$counter;
			$this->tpl->setCurrentBlock("mails");
			$this->tpl->setVariable("ROWCOL","tblrow".(($counter % 2)+1));
			$this->tpl->setVariable("MAIL_ID", $mail["mail_id"]);
		
			if(is_array($_POST["mail_id"]))
			{
				$this->tpl->setVariable("CHECKBOX_CHECKED",in_array($mail["mail_id"],$_POST["mail_id"]) ? 'checked' : "");
			}
		
			// GET FULLNAME OF SENDER
			
			if($_GET['mobj_id'] == $this->mbox->getSentFolder() ||
				$_GET['mobj_id'] == $this->mbox->getDraftsFolder())
			{
				if($mail['rcp_to'])
				{
					$this->tpl->setVariable("MAIL_LOGIN",$mail['rcp_to']);
				}
				else
				{
					$this->tpl->setVariable("MAIL_LOGIN",$this->lng->txt('not_available'));
				}
			}
			else
			{
				$tmp_user = new ilObjUser($mail["sender_id"]);
				$this->tpl->setVariable("MAIL_FROM", $tmp_user->getFullname());
				if(!($login = $tmp_user->getLogin()))
				{
					$login = $mail["import_name"]." (".$this->lng->txt("imported").")";
				}
				$pic_path = $tmp_user->getPersonalPicturePath("xxsmall");
				
				$this->tpl->setCurrentBlock("pers_image");
				$this->tpl->setVariable("IMG_SENDER", $pic_path);
				$this->tpl->setVariable("ALT_SENDER", $login);
				$this->tpl->parseCurrentBlock();
				$this->tpl->setCurrentBlock("mails");
		
				$this->tpl->setVariable("MAIL_LOGIN",$login);
			}
			$this->tpl->setVariable("MAILCLASS", $mail["m_status"] == 'read' ? 'mailread' : 'mailunread');
			// IF ACTUAL FOLDER IS DRAFT BOX, DIRECT TO COMPOSE MESSAGE
			if($_GET["mobj_id"] == $this->mbox->getDraftsFolder())
			{
				$this->ctrl->setParameterByClass("ilmailformgui", "mail_id", $mail["mail_id"]);
				$this->ctrl->setParameterByClass("ilmailformgui", "type", "draft");
				$this->tpl->setVariable("MAIL_LINK_READ", $this->ctrl->getLinkTargetByClass("ilmailformgui"));
				$this->ctrl->clearParametersByClass("ilmailformgui");
			}
			else
			{
				$this->ctrl->setParameter($this, "mail_id", $mail["mail_id"]);
				$this->ctrl->setParameter($this, "cmd", "showMail");
				$this->tpl->setVariable("MAIL_LINK_READ", $this->ctrl->getLinkTarget($this));
				$this->ctrl->clearParameters($this);
			}
			$this->tpl->setVariable("MAIL_SUBJECT", htmlspecialchars($mail["m_subject"]));
			$this->tpl->setVariable("MAIL_DATE", ilFormat::formatDate($mail["send_time"]));
			$this->tpl->parseCurrentBlock();
		}
		// END MAILS
		
		$mtree = new ilTree($ilUser->getId());
		$mtree->setTableNames('mail_tree','mail_obj_data');
		$folder_node = $mtree->getNodeData($_GET[mobj_id]);
		
		
		// folder_image
		if($folder_node["type"] == 'user_folder')
		{
			$this->tpl->setVariable("TXT_FOLDER", $folder_node["title"]);
			$this->tpl->setVariable("IMG_FOLDER", ilUtil::getImagePath("icon_user_folder.gif"));
		}
		else
		{
			$this->tpl->setVariable("TXT_FOLDER", $this->lng->txt("mail_".$folder_node["title"]));
			$this->tpl->setVariable("IMG_FOLDER", ilUtil::getImagePath("icon".substr($folder_node["title"], 1).".gif"));
		}
		$this->tpl->setVariable("TXT_MAIL", $this->lng->txt("mail"));
		$this->tpl->setVariable("TXT_MAIL_S", $this->lng->txt("mail_s"));
		$this->tpl->setVariable("TXT_UNREAD", $this->lng->txt("unread"));
		$this->tpl->setVariable("TXT_SUBMIT",$this->lng->txt("submit"));
		$this->tpl->setVariable("TXT_SELECT_ALL", $this->lng->txt("select_all"));
		$this->tpl->setVariable("IMGPATH",$this->tpl->tplPath);
		
		// MAIL SUMMARY
		$mail_counter = $this->umail->getMailCounterData();
		$this->tpl->setVariable("MAIL_COUNT", $mail_counter["total"]);
		$this->tpl->setVariable("MAIL_COUNT_UNREAD", $mail_counter["unread"]);
		$this->tpl->setVariable("TXT_UNREAD_MAIL_S",$this->lng->txt("mail_s_unread"));
		$this->tpl->setVariable("TXT_MAIL_S",$this->lng->txt("mail_s"));
		
		//columns headlines
		if($_GET['mobj_id'] == $this->mbox->getSentFolder() ||
			$_GET['mobj_id'] == $this->mbox->getDraftsFolder())
		{
			$this->tpl->setVariable("TXT_SENDER", $this->lng->txt("recipient"));
		}
		else
		{
			$this->tpl->setVariable("TXT_SENDER", $this->lng->txt("sender"));
		}
		$this->tpl->setVariable("TXT_SUBJECT", $this->lng->txt("subject"));
		//	$this->tpl->setVariable("MAIL_SORT_SUBJ","link");
		$this->tpl->setVariable("TXT_DATE",$this->lng->txt("date"));
		$this->tpl->setVariable("DIRECTION", "up");
		
		$this->tpl->show();
	}

	public function editFolder()
	{
		switch ($_POST["action"])
		{
			case 'markMailsRead':
				if(is_array($_POST["mail_id"]))
				{
					$this->umail->markRead($_POST["mail_id"]);
				}
				else
				{
					ilUtil::sendInfo($this->lng->txt("mail_select_one"));
				}
				break;
			case 'markMailsUnread':
				if(is_array($_POST["mail_id"]))
				{
					$this->umail->markUnread($_POST["mail_id"]);
				}
				else
				{
					ilUtil::sendInfo($this->lng->txt("mail_select_one"));
				}
				break;
	
			case 'deleteMails':
				// IF MAILBOX IS TRASH ASK TO CONFIRM
				if($this->mbox->getTrashFolder() == $_GET["mobj_id"])
				{
					if(!is_array($_POST["mail_id"]))
					{
						ilUtil::sendInfo($this->lng->txt("mail_select_one"));
						$this->errorDelete = true;
					}
					else
					{
						ilUtil::sendInfo($this->lng->txt("mail_sure_delete"));
					}
				} // END IF MAILBOX IS TRASH FOLDER
				else
				{
					// MOVE MAILS TO TRASH
					if(!is_array($_POST["mail_id"]))
					{
						ilUtil::sendInfo($this->lng->txt("mail_select_one"));
					}
					else if($this->umail->moveMailsToFolder($_POST["mail_id"], $this->mbox->getTrashFolder()))
					{
						$_GET["offset"] = 0;
						ilUtil::sendInfo($this->lng->txt("mail_moved_to_trash"));
					}
					else
					{
						ilUtil::sendInfo($this->lng->txt("mail_move_error"));
					}
				}
				break;
	
			case 'add':
				$this->ctrl->setParameterByClass("ilmailoptionsgui", "cmd", "add");
				$this->ctrl->redirectByClass("ilmailoptionsgui");
	
			case 'moveMails':
			default:
				if(!is_array($_POST["mail_id"]))
				{
					ilUtil::sendInfo($this->lng->txt("mail_select_one"));
				}
				else if($this->umail->moveMailsToFolder($_POST["mail_id"],$_POST["action"]))
				{
					ilUtil::sendInfo($this->lng->txt("mail_moved"));
				}
				else
				{
					ilUtil::sendInfo($this->lng->txt("mail_move_error"));
				}
				break;
		}
		
		$this->showFolder();
	}
	
	public function confirmDeleteMails()
	{
		// ONLY IF FOLDER IS TRASH, IT WAS ASKED FOR CONFIRMATION
		if($this->mbox->getTrashFolder() == $_GET["mobj_id"])
		{
			if(!is_array($_POST["mail_id"]))
			{
				ilUtil::sendInfo($this->lng->txt("mail_select_one"));
			}
			else if($this->umail->deleteMails($_POST["mail_id"]))
			{
				$_GET["offset"] = 0;
				ilUtil::sendInfo($this->lng->txt("mail_deleted"));
			}
			else
			{
				ilUtil::sendInfo($this->lng->txt("mail_delete_error"));
			}
		}
		
		$this->showFolder();
	}

	public function cancelDeleteMails()
	{
		$this->ctrl->setParameter($this, "offset", $_GET["offset"]);
		$this->ctrl->redirect($this);
	}

	public function showMail()
	{
		global $ilUser;

		if ($_SESSION["mail_id"])
		{
			$_GET["mail_id"] = $_SESSION["mail_id"];
			$_SESSION["mail_id"] = "";
		}

		$this->umail->markRead(array($_GET["mail_id"]));

		$mailData = $this->umail->getMail($_GET["mail_id"]);

		$this->tpl->addBlockFile("ADM_CONTENT", "adm_content", "tpl.mail_read.html", "Services/Mail");
		$this->tpl->setVariable("HEADER",$this->lng->txt("mail_mails_of"));
		
		//buttons
		$tplbtn = new ilTemplate("tpl.buttons.html", true, true);
		if($mailData["sender_id"])
		{
			$tplbtn->setCurrentBlock("btn_cell");
			$this->ctrl->setParameterByClass("ilmailformgui", "mail_id", $_GET["mail_id"]);
			$this->ctrl->setParameterByClass("ilmailformgui", "type", "reply");
			$tplbtn->setVariable("BTN_LINK", $this->ctrl->getLinkTargetByClass("ilmailformgui"));
			$this->ctrl->clearParametersByClass("iliasmailformgui");
			$tplbtn->setVariable("BTN_TXT", $this->lng->txt("reply"));
			$tplbtn->parseCurrentBlock();
		}
		$tplbtn->setCurrentBlock("btn_cell");
		$this->ctrl->setParameterByClass("ilmailformgui", "mail_id", $_GET["mail_id"]);
		$this->ctrl->setParameterByClass("ilmailformgui", "type", "forward");
		$tplbtn->setVariable("BTN_LINK", $this->ctrl->getLinkTargetByClass("ilmailformgui"));
		$this->ctrl->clearParametersByClass("iliasmailformgui");
		$tplbtn->setVariable("BTN_TXT", $this->lng->txt("forward"));
		$tplbtn->parseCurrentBlock();
		$tplbtn->setCurrentBlock("btn_cell");
		$this->ctrl->setParameter($this, "mail_id", $_GET["mail_id"]);
		$this->ctrl->setParameter($this, "cmd", "printMail");
		$tplbtn->setVariable("BTN_LINK", $this->ctrl->getLinkTarget($this));
		$this->ctrl->clearParameters($this);
		$tplbtn->setVariable("BTN_TXT", $this->lng->txt("print"));
		$tplbtn->setVariable("BTN_TARGET","target=\"_blank\"");
		$tplbtn->parseCurrentBlock();
		if($mailData["sender_id"])
		{
			$tplbtn->setCurrentBlock("btn_cell");
			$this->ctrl->setParameter($this, "mail_id", $_GET["mail_id"]);
			$this->ctrl->setParameter($this, "cmd", "add");
			$tplbtn->setVariable("BTN_LINK", $this->ctrl->getLinkTarget($this));
			$this->ctrl->clearParameters($this);
			$tplbtn->setVariable("BTN_TXT", $this->lng->txt("mail_add_to_addressbook"));
			$tplbtn->parseCurrentBlock();
		}
		$tplbtn->setCurrentBlock("btn_cell");
		$this->ctrl->setParameter($this, "mail_id", $_GET["mail_id"]);
		$this->ctrl->setParameter($this, "action", "deleteMails");
		$tplbtn->setVariable("BTN_LINK", $this->ctrl->getLinkTarget($this));
		$this->ctrl->clearParameters($this);
		$tplbtn->setVariable("BTN_TXT", $this->lng->txt("delete"));
		$tplbtn->parseCurrentBlock();
		
		$tplbtn->setCurrentBlock("btn_row");
		$tplbtn->parseCurrentBlock();
		
		$this->tpl->setVariable("BUTTONS2",$tplbtn->get());
		$this->ctrl->setParameter($this, "cmd", "post");
		$this->ctrl->setParameter($this, "mail_id", $_GET["mail_id"]);
		$this->tpl->setVariable("ACTION", $this->ctrl->getLinkTarget($this));
		$this->ctrl->clearParameters($this);
		
		// SET MAIL DATA
		$counter = 1;
		// FROM
		$this->tpl->setVariable("TXT_FROM", $this->lng->txt("from"));
		
		$tmp_user = new ilObjUser($mailData["sender_id"]);
		#$tmp_user =& ilObjectFactory::getInstanceByObjId($mailData["sender_id"],false);
		
		$this->tpl->setVariable("FROM", $tmp_user->getFullname());
		$this->tpl->setCurrentBlock("pers_image");
		$this->tpl->setVariable("IMG_SENDER", $tmp_user->getPersonalPicturePath("xsmall"));
		$this->tpl->setVariable("ALT_SENDER", $tmp_user->getFullname());
		$this->tpl->parseCurrentBlock();
		$this->tpl->setCurrentBlock("adm_content");
		
		if(!($login = $tmp_user->getLogin()))
		{
			$login = $mailData["import_name"]." (".$this->lng->txt("imported").")";
		}
		$this->tpl->setVariable("MAIL_LOGIN",$login);
		$this->tpl->setVariable("CSSROW_FROM",++$counter%2 ? 'tblrow1' : 'tblrow2');
		// TO
		$this->tpl->setVariable("TXT_TO", $this->lng->txt("mail_to"));
		$this->tpl->setVariable("TO", $mailData["rcp_to"]);
		$this->tpl->setVariable("CSSROW_TO",(++$counter)%2 ? 'tblrow1' : 'tblrow2');
		
		// CC
		if($mailData["rcp_cc"])
		{
			$this->tpl->setCurrentBlock("cc");
			$this->tpl->setVariable("TXT_CC",$this->lng->txt("cc"));
			$this->tpl->setVariable("CC",$mailData["rcp_cc"]);
			$this->tpl->setVariable("CSSROW_CC",(++$counter)%2 ? 'tblrow1' : 'tblrow2');
			$this->tpl->parseCurrentBlock();
		}
		// SUBJECT
		$this->tpl->setVariable("TXT_SUBJECT",$this->lng->txt("subject"));
		$this->tpl->setVariable("SUBJECT",htmlspecialchars($mailData["m_subject"]));
		$this->tpl->setVariable("CSSROW_SUBJ",(++$counter)%2 ? 'tblrow1' : 'tblrow2');
		
		// DATE
		$this->tpl->setVariable("TXT_DATE", $this->lng->txt("date"));
		$this->tpl->setVariable("DATE", ilFormat::formatDate($mailData["send_time"]));
		$this->tpl->setVariable("CSSROW_DATE",(++$counter)%2 ? 'tblrow1' : 'tblrow2');
		
		// ATTACHMENTS
		if($mailData["attachments"])
		{
			$this->tpl->setCurrentBlock("attachment");
			$this->tpl->setCurrentBlock("a_row");
			$counter = 1;
			foreach($mailData["attachments"] as $file)
			{
				$this->tpl->setVariable("A_CSSROW",++$counter%2 ? 'tblrow1' : 'tblrow2');
				$this->tpl->setVariable("FILE",$file);
				$this->tpl->setVariable("FILE_NAME",$file);
				$this->tpl->parseCurrentBlock();
			}
			$this->tpl->setVariable("TXT_ATTACHMENT",$this->lng->txt("attachments"));
			$this->tpl->setVariable("TXT_DOWNLOAD",$this->lng->txt("download"));
			$this->tpl->parseCurrentBlock();
		}
		
		// MESSAGE
		$this->tpl->setVariable("TXT_MESSAGE", $this->lng->txt("message"));
		$this->tpl->setVariable("MAIL_MESSAGE", nl2br(ilUtil::makeClickable($mailData["m_message"])));
		//$this->tpl->setVariable("MAIL_MESSAGE", nl2br(ilUtil::makeClickable(htmlspecialchars($mailData["m_message"]))));
		
		$this->tpl->show();
	}

	public function printMail()
	{
		$tplprint = new ilTemplate("tpl.mail_print.html",true,true,true);
		$tplprint->setVariable("JSPATH",$tpl->tplPath);
		
		//get the mail from user
		$mailData = $this->umail->getMail($_GET["mail_id"]);
		
		// SET MAIL DATA
		// FROM
		$tplprint->setVariable("TXT_FROM", $this->lng->txt("from"));
		
		$tmp_user = new ilObjUser($mailData["sender_id"]); 
		if(!($login = $tmp_user->getFullname()))
		{
			$login = $mailData["import_name"]." (".$this->lng->txt("imported").")";
		}
		$tplprint->setVariable("FROM", $login);
		// TO
		$tplprint->setVariable("TXT_TO", $this->lng->txt("mail_to"));
		$tplprint->setVariable("TO", $mailData["rcp_to"]);
		
		// CC
		if($mailData["rcp_cc"])
		{
			$tplprint->setCurrentBlock("cc");
			$tplprint->setVariable("TXT_CC",$this->lng->txt("cc"));
			$tplprint->setVariable("CC",$mailData["rcp_cc"]);
			$tplprint->parseCurrentBlock();
		}
		// SUBJECT
		$tplprint->setVariable("TXT_SUBJECT",$this->lng->txt("subject"));
		$tplprint->setVariable("SUBJECT",htmlspecialchars($mailData["m_subject"]));
		
		// DATE
		$tplprint->setVariable("TXT_DATE", $this->lng->txt("date"));
		$tplprint->setVariable("DATE", ilFormat::formatDate($mailData["send_time"]));
		
		// MESSAGE
		$tplprint->setVariable("TXT_MESSAGE", $this->lng->txt("message"));
		$tplprint->setVariable("MAIL_MESSAGE", nl2br(htmlspecialchars($mailData["m_message"])));
		
		
		$tplprint->show();
	}

	function deliverFile()
	{
		if ($_SESSION["mail_id"])
		{
			$_GET["mail_id"] = $_SESSION["mail_id"];
		}
		$_SESSION["mail_id"] = "";

		$filename = ($_SESSION["filename"]
						? $_SESSION["filename"]
						: ($_POST["filename"]
							? $_POST["filename"]
							: $_GET["filename"]));
		$_SESSION["filename"] = "";

		if ($filename != "")
		{
			require_once "classes/class.ilFileDataMail.php";
			
			$mfile = new ilFileDataMail($_SESSION["AccountId"]);
			if(!$path = $mfile->getAttachmentPath($filename, $_GET["mail_id"]))
			{
				ilUtil::sendInfo($this->lng->txt("mail_error_reading_attachment"));
				$this->showMail();
			}
			else
			{
				ilUtil::deliverFile($path, $filename);
			}
		}
		else
		{
			ilUtil::sendInfo($this->lng->txt("mail_select_attachment"));
			$this->showMail();
		}
	}

}

?>