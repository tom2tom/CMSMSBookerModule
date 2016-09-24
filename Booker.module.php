<?php
#------------------------------------------------------------------------
# Module: Booker - a resource booking module for CMS Made Simple
# Mostly copyright (C) 2015-2016 Tom Phane <@>
# This project's forge-page is: http://dev.cmsmadesimple.org/projects/booker
#
# This module is free software; you can redistribute it and/or modify it under
# the terms of the GNU Affero General Public License as published by the Free
# Software Foundation; either version 3 of the License, or (at your option)
# any later version.
#
# This module is distributed in the hope that it will be useful, but
# WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU Affero General Public License (www.gnu.org/licenses/licenses.html#AGPL)
# for more details
#-----------------------------------------------------------------------

define('DBGBKG', FALSE);

class Booker extends CMSModule
{
	const MINGRPID = 10000; //lowest id in items-table for a group
	const USERSTYLES = 5; //no. of styles available for bookings-table styling

	//sub-group allocation protocol
	const ALLOCNONE = 0;
	const ALLOCFIRST = 1;
	const ALLOCRAND = 2;
	const ALLOCROTE = 3;
	const ALLOCCHOOSE = 4;
	/*list-display formats
	LISTSU group by slotstart, show each interval, [resource,] user
	LISTSR group by slotstart, show each user [,resource,] interval i.e. ~same as LISTSU unless is group
	[LISTRS group by resource, show each interval, user] i.e. only for groups
	LISTUS group by user, show each interval [,resource]
	*/
	const LISTSU = 1;
	const LISTSR = 2; //default for imported groups
	const LISTRS = 3;
	const LISTUS = 4;
	//bookings-table-column durations
	const SEGDAY = 0;
	const SEGWEEK = 1;
	const SEGMTH = 2;
//	const SEGYR = 3;
	//booking-display-periods
	const RANGEDAY = 0;
	const RANGEWEEK = 1;
	const RANGEMTH = 2;
	const RANGEYR = 3;
	//HistoryTable status codes
	const STATNONE = 0;//unknown/normal/default
	//request-stage
	const STATNEW = 1;//new, approver consideration pending
	const STATCHG = 2;//change request, approver consideration pending
	const STATDEL = 3;//delete request, approver consideration pending
	const STATTELL = 4;//further information submitted
	const STATASK = 5;//booker queried, waiting for response
 	const STATCANCEL = 6;//abandoned by user or admin on user's behalf
	const STATMAXREQ = 9;//last-recognised request-status value
	//later status
	const STATOK = 20;//aka APPROVED done/processed
	const STATADMINREC = 21;//booking recorded by admin
	const STATSELFREC = 22;//recorded by approved user (i.e. no request)

	const STATMAXOK = 30;//last-recognised request-done-ok value
	const STATTEMP = 31;//user-recorded, pending admin confirmation
	const STATDEFERRED = 40;//booking to be re-scheduled, per user request or admin imposition
 	const STATGONE = 90;//deletion pending, while its historical data needed
	//problems
	const STATBIG = 80;//too many slots requested
	const STATDEFER = 81;//request not yet processed cuz' too far ahead
	const STATLATE = 82;//request past or not far-enough ahead
	const STATNA = 83;//resouce N/A at requested time, cannot accept
	const STATDUP = 84;//duplicate request, cannot accept
 	const STATERR = 85;//system error while processing
 	const STATRETRY = 86;//some temporary problem, try again later
	const STATFAILED = 89;//generic request-failure
	//HistoryTable payment codes
	const STATFREE = 0;//no fee for use
	const STATPAYABLE = 1;//fee applies, not yet paid
	const STATPAID = 2;//fee pre- or post-paid
	const STATCREDITED = 3;//fee to be paid upon request
 	const STATNOTPAID = 9;//payable but unpaid for some non-credit-related reason
	const STATCREDITUSED = 10;//past credit offset against other use
	const STATCREDITEXPIRED = 11;//past credit timed out
	const STATCREDITADDED = 12;//prepayment amount
	//cache-key seed/prefixes
	const CARTKEY = 'bkr_Cart';
	const PARMKEY = 'bkr_Parm';
	const SESSIONKEY = 'bkr_Sess';
	const PATNADDRESS = '/^.+@.+\..+$/';
	const PATNPHONE = '/^(\+\d{1,4} *)?[\d ]{5,15}$/';

	public $dbHandle; //cached connection to adodb
//	public $AvailTable; //resource-availabilty cache
	public $BookerTable; //booker details
	public $DataTable; //non-repeated bookings-data
	public $FeeTable; //payment amounts/rates and associated conditions
	public $GroupTable; //group-relationships
	public $HistoryTable; //bookings history
	public $ItemTable; //resources and resource-groups
	public $RepeatTable; //repeated bookings-data
//	public $CacheTable; //cached bookings-data
	public $UserTable; //admin users (who may 'own' resource/group)
	public $before20;
	public $havemcrypt;

	protected $PermStructName = 'Booker Module Admin';
	protected $PermAdminName = 'Booker Admin';
	protected $PermSeeName = 'Booker View';
	protected $PermEditName = 'Booker Modify';
	protected $PermAddName = 'Booker Resource Add';
	protected $PermModName = 'Booker Resource Modify';
	protected $PermDelName = 'Booker Resource Delete';
	protected $PermPerName = 'Booker User Modify';

	public function __construct()
	{
		parent::__construct();

		$this->RegisterModulePlugin(TRUE);

		$this->dbHandle = cmsms()->GetDb();
		$pre = cms_db_prefix();
//		$this->AvailTable = $pre.'module_bkr_avail';
		$this->BookerTable = $pre.'module_bkr_bookers';
		$this->DataTable = $pre.'module_bkr_data';
		$this->FeeTable = $pre.'module_bkr_fees';
		$this->GroupTable = $pre.'module_bkr_groups';
		$this->HistoryTable = $pre.'module_bkr_history';
		$this->ItemTable = $pre.'module_bkr_items';
		$this->RepeatTable = $pre.'module_bkr_repeats';
//		$this->CacheTable = $pre.'module_bkr_cache';
		$this->UserTable = $pre.'users';
		global $CMS_VERSION;
		$this->before20 = (version_compare($CMS_VERSION,'2.0') < 0);
		$this->havemcrypt = function_exists('mcrypt_encrypt');

		spl_autoload_register(array($this,'cmsms_spacedload'));
	}

	public function __destruct()
	{
		spl_autoload_unregister(array($this,'cmsms_spacedload'));
		if (function_exists('parent::__destruct'))
			parent::__destruct();
	}

	/* namespace autoloader - CMSMS default autoloader doesn't do spacing */
	private function cmsms_spacedload($class)
	{
		$prefix = get_class().'\\'; //our namespace prefix
		// ignore if $class doesn't have that
		if (($p = strpos($class,$prefix)) === FALSE)
			return;
		if (!($p === 0 || ($p === 1 && $class[0] == '\\')))
			return;
		// get the relative class name
		$len = strlen($prefix);
		if ($class[0] == '\\') {
			$len++;
		}
		$relative_class = trim(substr($class,$len),'\\');
		if (($p = strrpos($relative_class,'\\',-1)) !== FALSE) {
			$relative_dir = str_replace('\\',DIRECTORY_SEPARATOR,$relative_class);
			$base = substr($relative_dir,$p+1);
			$relative_dir = substr($relative_dir,0,$p).DIRECTORY_SEPARATOR;
		} else {
			$base = $relative_class;
			$relative_dir = '';
		}
		// directory for the namespace
		$bp = __DIR__.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.$relative_dir;
		$fp = $bp.'class.'.$base.'.php';
		if (file_exists($fp)) {
			include $fp;
		} elseif ($relative_dir) {
			$fp = $bp.$base.'.php';
			if (file_exists($fp))
				include $fp;
		}
	}

	public function AllowAutoInstall()
	{
		return FALSE;
	}

	public function AllowAutoUpgrade()
	{
		return FALSE;
	}

	//for 1.11+
	public function AllowSmartyCaching()
	{
		return FALSE;
	}

	public function GetName()
	{
		return 'Booker';
	}

	public function GetFriendlyName()
	{
		return $this->Lang('friendlyname');
	}

	public function GetHelp()
	{
		$fn = cms_join_path(__DIR__,'css','public.css');
		$cont = @file_get_contents($fn);
		if ($cont) {
			$example = preg_replace(array('~\s?/\*(.*)?\*/~Usm','~\s?//.*$~m'),array('',''),$cont);
			$example = str_replace(array("\n\n","\n","\t"),array('<br />','<br />',' '),trim($example));
		} else
			$example = $this->Lang('missing');
		return $this->Lang('help',$example);
	}

	public function GetVersion()
	{
		return '0.1';
	}

	public function GetAuthor()
	{
		return 'tomphantoo';
	}

	public function GetAuthorEmail()
	{
		return 'tpgww@onepost.net';
	}

	public function GetChangeLog()
	{
		$fn = cms_join_path(__DIR__,'include','changelog.inc');
		return @file_get_contents($fn);
	}

	public function IsPluginModule()
	{
		return TRUE;
	}

	public function HasAdmin()
	{
		return TRUE;
	}

	/*
	LazyLoadAdmin() for 1.10 and later
	*/
	public function LazyLoadAdmin()
	{
		return FALSE;
	}

	public function GetAdminSection()
	{
		return 'content';
	}

	public function GetAdminDescription()
	{
		return $this->Lang('moddescription');
	}

	public function VisibleToAdminUser()
	{
		return $this->_CheckAccess();
	}

/*public function AdminStyle()
	{
	}
*/
	public function GetHeaderHTML()
	{
		return '<link rel="stylesheet" type="text/css" id="adminstyler" href="'.$this->GetModuleURLPath().'/css/admin.css" />';
	}

	public function SuppressAdminOutput(&$request)
	{
		//prevent output of general admin content when doing an export,
		//and when processing an ajax call
		if (isset($request['mact'])) {
			if (strpos($request['mact'],'exportbooking',6)) return TRUE;
			if (isset($request['m1_export'])) return TRUE;
			if (isset($request['m1_exportbkg'])) return TRUE;
			if (strpos($request['mact'],'sortlike',6)) return TRUE;
		}
		return FALSE;
	}

	public function GetDependencies()
	{
		return array();
	}

	/*
	LazyLoadFrontend() for 1.10 and later
	*/
	public function LazyLoadFrontend()
	{
		return TRUE;
	}

	public function MinimumCMSVersion()
	{
		return '1.10'; //CHECKME
	}

/*public function MaximumCMSVersion()
	{
		return '1.12.99';
	}
*/
	public function InstallPostMessage()
	{
		return $this->Lang('postinstall');
	}

	public function UninstallPreMessage()
	{
		return $this->Lang('really_uninstall');
	}

	public function UninstallPostMessage()
	{
		return $this->Lang('postuninstall');
	}

	/*
	SetParameters() for pre-1.10
	*/
	public function SetParameters()
	{
		$this->InitializeAdmin();
		$this->InitializeFrontend();
	}

	/*
	InitializeFrontend() partial setup for 1.10
	*/
	public function InitializeFrontend()
	{
		$this->RestrictUnknownParams();
		//TODO parameter types
		$this->SetParameterType('account',CLEAN_STRING);
		$this->SetParameterType('apply',CLEAN_STRING); //change view enum
		$this->SetParameterType('bookat',CLEAN_INT);
		$this->SetParameterType('bookertype',CLEAN_INT);
		$this->SetParameterType('bkgid',CLEAN_INT);
		$this->SetParameterType('calendarinput',CLEAN_STRING);
		$this->SetParameterType('cancel',CLEAN_NONE);
		$this->SetParameterType('captcha',CLEAN_STRING);
		$this->SetParameterType('cart',CLEAN_NONE);
		$this->SetParameterType('cartcomment',CLEAN_STRING); //array of comments
		$this->SetParameterType('cartsel',CLEAN_STRING); //array of keys
		$this->SetParameterType('itempick',CLEAN_INT);
		$this->SetParameterType('clickat',CLEAN_STRING);
		$this->SetParameterType('comment',CLEAN_STRING); //booking-request parameters
		$this->SetParameterType('contact',CLEAN_STRING);
		$this->SetParameterType('contactnew',CLEAN_STRING);
		$this->SetParameterType('delete',CLEAN_NONE); //cart-item action
		$this->SetParameterType('find',CLEAN_NONE);
		$this->SetParameterType('findpick',CLEAN_INT);
		$this->SetParameterType('findfirst',CLEAN_STRING);
		$this->SetParameterType('findlast',CLEAN_STRING);
		$this->SetParameterType('finduser',CLEAN_STRING);
		$this->SetParameterType('findusertype',CLEAN_INT);
		$this->SetParameterType('firstpick',CLEAN_INT);
		$this->SetParameterType('item',CLEAN_STRING); //id or alias
		$this->SetParameterType('item_id',CLEAN_INT); //for zooms
		$this->SetParameterType('itemkeys',CLEAN_STRING);
		$this->SetParameterType('listformat',CLEAN_INT); //list-format enum
		$this->SetParameterType('message',CLEAN_STRING);
		$this->SetParameterType('name',CLEAN_STRING);
		$this->SetParameterType('newlist',CLEAN_INT); //list-format change boolean
		$this->SetParameterType('origreturnid',CLEAN_INT); //something? related to captcha module
		$this->SetParameterType('pagerows',CLEAN_INT); //table-pager value
		$this->SetParameterType('passwd',CLEAN_STRING);
		$this->SetParameterType('range',CLEAN_STRING); //enum or period-name
		$this->SetParameterType('rangepick',CLEAN_INT); //change view-range enum
		$this->SetParameterType('register',CLEAN_NONE);
		$this->SetParameterType('request',CLEAN_NONE);
		$this->SetParameterType('requesttype',CLEAN_INT);
		$this->SetParameterType('search',CLEAN_NONE);
		$this->SetParameterType('searchsel',CLEAN_NONE);
		$this->SetParameterType('slide',CLEAN_INT); //value matches button label
//		$this->SetParameterType('slotlen',CLEAN_INT);
//		$this->SetParameterType('slotstart',CLEAN_INT);
		$this->SetParameterType('showfrom',CLEAN_STRING);
//		$this->SetParameterType('paramskey',CLEAN_STRING);
		$this->SetParameterType('subgrpcount',CLEAN_INT);
		$this->SetParameterType('submit',CLEAN_NONE);
		$this->SetParameterType('task',CLEAN_STRING);
		$this->SetParameterType('toggle',CLEAN_NONE);
		$this->SetParameterType('until',CLEAN_STRING);
//		$this->SetParameterType('user',CLEAN_STRING);
		$this->SetParameterType('view',CLEAN_STRING); //table or list
		$this->SetParameterType('when',CLEAN_STRING);
		$this->SetParameterType('zoomin',CLEAN_NONE);
		$this->SetParameterType('zoomout',CLEAN_NONE);
		$this->SetParameterType(CLEAN_REGEXP.'/bkr_.*/',CLEAN_STRING);
		/* register 'routes' to use for pretty url parsing
		these regexes translate url-parameter(s) to $param[](s) be supplied
		to the specified actions (default calls ->DisplayModuleOutput())
		so the routes need to conform to parameter-usage in handler-func(s).
		(?P<name>regex) captures the text matched by "regex" into the group "name",
		which can contain letters and numbers but must start with a letter.
		See also: Booker\Utils->GetLink() which needs to conform to this.
		*/
		// display bookings for a specific group/item TODO make this work
		$this->RegisterRoute('/[Bb]ook(ings)?\/(group|item)?(?P<item>.+)$/',array('action' => 'default'));
	}

	/*
	InitializeAdmin() partial setup for 1.10
	*/
	public function InitializeAdmin()
	{
		$this->CreateParameter('item','',$this->Lang('help_item'));
		$this->CreateParameter('showfrom','',$this->Lang('help_showfrom'));
		$this->CreateParameter('range',$this->Lang('week'),$this->Lang('help_range'));
		$this->CreateParameter('view','table',$this->Lang('help_view'));
	}

	/*
	get_tasks:
	Specify the tasks that this module uses
	Returns: CmsRegularTask-compliant object, or array of them
	*/
	public function get_tasks()
	{
		return array(
			new Booker\Cleanold_task(),
			new Booker\Clearcache_task()
		);
	}

	/**
	DoAction:
	@action:
	@id: session identifier
	@params:
	@returnid:
	No permission-checks are done here or in related action files, as capabilities
	are governed by which actionable widgets are displayed
	- and those are permission-checked before creation
	*/
	public function DoAction($action, $id, $params, $returnid=-1)
	{
		switch ($action) {
		 case 'add':
		 case 'copy':
		 case 'edit':
		 case 'see':
		 case 'update': //apply/submit item/group changes
 			if (isset($params['modfee'])) //in-page edit-fees button clicked
				$action = 'openfees';
			elseif (isset($params['sortlike']))
				$action = 'sortlike';
			else
				$action = 'openitem';
			break;
		 case 'toggle': //[de]activate
			$this->_ActivateItem($id, $params, $returnid); //trivial func, don't bother with separate action file
			$action = 'defaultadmin';
			break;
		 case 'announce':
		 case 'default':
		 case 'defaultadmin':
		 case 'delete':
		 case 'exportbooking':
		 case 'findbooking':
		 case 'import':
		 case 'notifybooker':
		 case 'openbooker':
		 case 'openitem':
		 case 'openbooking':
		 case 'openrequest':
		 case 'requestbooking':
		 case 'openfees':
		 case 'opencart':
		 case 'setprefs':
		 case 'swapgroups':
		 case 'sortlike':
			break;
		 case 'bookerbookings':
		 case 'itembookings':
			if (isset($params['importbkg']))
				$action = 'import';
			break;
		 case 'adminbooker':
			if (isset($params['importbkr']))
				$action = 'import';
			break;
/*		 case 'adminbooking':
			if (isset($params['importbkg']))
				$action = 'import';
			elseif (isset($params['find']))
				$action = 'findbooking'; //TODO admin, not frontend
			else
				$action = 'processrequest';
			break;
*/
		 case 'processitem': //multiple/selected/?export?/delete etc
			if (isset($params['setfees']))
				$action = 'openfees';
			elseif (isset($params['importitm']) || isset($params['importfee']))
				$action = 'import';
			break;
		 case 'processrequest':
			if (isset($params['importbkg']))
				$action = 'import';
			break;
/*		 case 'approve':
		 case 'reject':
		//TODO others
		 case 'rapprove':
		 case 'rreject':
		 case 'rdelete':
		 case 'redit':
		 case 'rnotify':
		 case 'rsee':
			$action = 'processrequest';
			break;
*/
		 case 'addfee':
		 case 'delfee':
		 case 'modfee':
			$action = 'openfees';
			break;
		 default:
			if (isset($params['active_tab'])) //TODO if backend
				$action = 'defaultadmin';
			else
				return;
		}
		parent::DoAction($action,$id,$params,$returnid);
	}

	/**
	_CheckAccess($permission='',$warn=FALSE)
		NOT PART OF THE MODULE API
	*/
	public function _CheckAccess($permission='', $warn=FALSE)
	{
		switch ($permission) {
		 case '': //anything relevant
			$name = '';
			$ok = $this->CheckPermission($this->PermAdminName);
			if (!$ok) $ok = $this->CheckPermission($this->PermSeeName);
			if (!$ok) $ok = $this->CheckPermission($this->PermEditName);
			if (!$ok) $ok = $this->CheckPermission($this->PermPerName);
			if (!$ok) $ok = $this->CheckPermission($this->PermAddName);
			if (!$ok) $ok = $this->CheckPermission($this->PermDelName);
			if (!$ok) $ok = $this->CheckPermission($this->PermModName);
			if (!$ok) $ok = $this->CheckPermission($this->PermStructName);
			break;
		//bookings
		 case 'view':
			$name = $this->PermSeeName;
			$ok = $this->CheckPermission($name);
			break;
		 case 'book':
			$name = $this->PermEditName;
			$ok = $this->CheckPermission($name);
			break;
		 case 'admin':
			$name = $this->PermAdminName;
			$ok = $this->CheckPermission($name);
			break;
		//bookers
		 case 'booker':
			$name = $this->PermPerName;
			$ok = $this->CheckPermission($name);
			break;
		//resources
		 case 'add':
			$name = $this->PermAddName;
			$ok = $this->CheckPermission($name);
			break;
		 case 'modify':
			$name = $this->PermModName;
			$ok = $this->CheckPermission($name);
			break;
		 case 'delete':
			$name = $this->PermDelName;
			$ok = $this->CheckPermission($name);
			break;
		//module
		 case 'module':
			$name = $this->PermStructName;
			$ok = $this->CheckPermission($name);
			break;
		 default:
			$name = '';
			$ok = FALSE;
		}
		if (!$ok && $warn) {
			if ($name == '') $name = $this->Lang('perm_some');
			echo '<p class="error">'.$this->Lang('accessdenied',$name).'</p>';
		}
		return $ok;
	}

	/**
	_BuildNav:
	Generate XHTML page-change links
	@id: session identifier
	@returnid:
	@resume: name of action, or ordered array of them
	$params: reference to array of request-parameters including link-related data
	Returns: string, maybe ''
	*/
	public function _BuildNav($id, $returnid, $resume, &$params)
	{
		if (!is_array($resume)) {
			$resume = array($resume);
			$ic = 1;
		} else {
			$ic = count($resume);
		}
		$navstr = '';
		for ($i=0; $i<$ic; $i++) {
			$name = $resume[$i];
			$xtra = array();
			switch($name) {
			 case 'itembookings':
				$key = 'title_bookings';
				$xtra = array('item_id'=>$params['item_id'],'task'=>$params['task']);
				break;
			 case 'bookerbookings':
				$key = 'title_bookings';
				$xtra = array('booker_id'=>$params['booker_id'],'task'=>$params['task']);
				break;
			 default:
				$name = 'defaultadmin';
				$key = 'back_module';
				if (isset($params['active_tab'])) {
					$xtra = array('active_tab'=>$params['active_tab']);
				}
				break;
			}
			$navstr .= $this->CreateLink($id,$name,$returnid,'&#171; '.$this->Lang($key),$xtra);
		}
		return $navstr;
	}

	/* *
	_GetActiveTab(&$params)
	Get name of active tab (in a multi-tab page)
	*/
/*public function _GetActiveTab(&$params)
	{
		if (empty($params['active_tab']))
			return 'data';
		else
			return $params['active_tab'];
	}
*/
	/**
	_PrettyMessage:
	@text: text to display, or if @key = TRUE, a lang-key for the text to display
	@success: optional default TRUE whether to style message as positive
	@key: optional default TRUE whether @text is a lang key or raw
	*/
	public function _PrettyMessage($text, $success=TRUE, $key=TRUE)
	{
		$base = ($key) ? $this->Lang($text) : $text;
		if ($success)
			return $this->ShowMessage($base);
		else {
			$msg = $this->ShowErrors($base);
//			if ($faillink == FALSE) {
				//strip the link
				$pos = strpos($msg,'<a href=');
				$part1 = ($pos !== FALSE) ? substr($msg,0,$pos) : '';
				$pos = strpos($msg,'</a>',$pos);
				$part2 = ($pos !== FALSE) ? substr($msg,$pos+4) : $msg;
				$msg = $part1.$part2;
//			}
			return $msg;
		}
	}

	/**
	_CreateInputLinks:
	Generate xhtml for image and/or submit input(s) which can be styled like an icon
	and/or link, using class "fakeicon" for an image, "fakelink" for a standard link.
	Such object(s) is(are) needed where the handler/action requires all form data,
	instead of just the data for the oject itself (which happens for a normal link)
	@id: session identifier
	@name: name of action to be performed when (either of) the object(s) is clicked
	@iconfile: optional name of theme icon, or module-relative or absolute URL
	  of some other icon for image input, default FALSE i.e. no image
	@link: optional whether to (also) create a submit input, default FALSE
	@text: optional title and tip for an image, or mandatory displayed text for a link, default ''
	@extra: optional additional text that should be added into the object, default ''
	*/
	public function _CreateInputLinks($id, $name, $iconfile=FALSE, $link=FALSE, $text='', $extra='')
	{
		if ($iconfile) {
			$p = strpos($iconfile,'/');
			if ($p === FALSE) {
				$theme = ($this->before20) ? cmsms()->get_variable('admintheme'):
					cms_utils::get_theme_object();
				$imgstr = $theme->DisplayImage('icons/system/'.$iconfile,$text,'','','fakeicon systemicon');
				//trim string like <img src="..." class="fakeicon systemicon" alt="$text" title="$text" />
				$imgstr = str_replace(array('<img','/>'),array('',''),$imgstr);
			} elseif ($p == 0) {
				$imgstr = $this->GetModuleURLPath().$iconfile; } elseif (strpos($iconfile,'://',$p-1) === $p-1) {
				$imgstr = $iconfile; } else {
				$imgstr = $this->GetModuleURLPath().'/'.$iconfile; }
			$ret = '<input type="image" '.$imgstr.' name="'.$id.$name.'"'; //conservative assumption about spaces
			if ($extra)
				$ret .= ' '.$extra;
			$ret .= ' />';
		} else
			$ret = '';
		if ($link && $text) {
			if ($ret)
				$ret .=' ';
			$ret .='<input type="submit" value="'.$text.'" name="'.$id.$name.'" class="fakelink"';
			if ($extra)
				$ret .= ' '.$extra;
			$ret .= ' />';
		}
		return $ret;
	}

	/**
	_ActivateItem:
	@id: session identifier
	@params: array of parameters for the action
	@returnid:
	[de]activate the item passed in @params
	See also: Booker\Itemops::ToggleItemActive()
	*/
	public function _ActivateItem($id, &$params, $returnid)
	{
		if (isset($params['item_id'])) {
			$qdata = array();
			if (isset($params['active'])) {
				if ($params['active'])
					$qdata[] = 0;
				else
					$qdata[] = 1;
			} else
				$qdata[] = 0;
			$qdata[] = $params['item_id'];

			$sql = 'UPDATE '.$this->ItemTable.' SET active=? WHERE item_id=?';
	    	$this->dbHandle->Execute($sql,$qdata);

			if ($params['item_id'] < Booker::MINGRPID)
				$params = array('active_tab' => 'items');
			else
				$params = array('active_tab' => 'groups');
		} else
			$params = array('active_tab' => 'items');
	}
}
