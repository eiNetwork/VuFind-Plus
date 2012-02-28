<?php
/**
 *
 * Copyright (C) Anythink Libraries 2012.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 * 
 * @author Mark Noble <mnoble@turningleaftech.com>
 * @copyright Copyright (C) Anythink Libraries 2012.
 *
 */

require_once "Action.php";
require_once 'sys/MaterialsRequest.php';

/**
 * MaterialsRequest MyRequests Page, displays materials request information for the active user.
 */
class MyRequests extends Action
{

	function launch()
	{
		global $configArray;
		global $interface;
		global $user;
		
		$showOpen = true;
		if (isset($_REQUEST['requestsToShow']) && $_REQUEST['requestsToShow'] == 'allRequests'){
			$showOpen  = false;
		}
		$interface->assign('showOpen', $showOpen);
		
		//Get a list of all materials requests for the user
		$allRequests = array();
		if ($user){
			$materialsRequests = new MaterialsRequest();
			$materialsRequests->createdBy = $user->id;
			$materialsRequests->orderBy('title, dateCreated');
			if ($showOpen){
				$materialsRequests->whereAdd("status in ('pending', 'referredToILL', 'ILLplaced', 'notEnoughInfo')");
			}
			$materialsRequests->find();
			while ($materialsRequests->fetch()){
				$allRequests[] = clone $materialsRequests;
			}
		}else{
			$interface->assign('error', "You must be logged in to view your requests.");
		}
		$interface->assign('allRequests', $allRequests);

		$interface->setTemplate('myMaterialRequests.tpl');
		$interface->setPageTitle('My Materials Requests');
		$interface->display('layout.tpl');
	}
}