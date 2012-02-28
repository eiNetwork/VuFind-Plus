<?php
/**
 *
 * Copyright (C) Villanova University 2007.
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
 */

require_once 'Action.php';

class AJAX extends Action {

	function launch()
	{
		$method = $_REQUEST['method'];
		if (in_array($method, array('GetAutoSuggestList', 'GetRatings', 'RandomSysListTitles', 'SysListTitles', 'GetListTitles'))){
			header('Content-type: text/plain');
			header('Cache-Control: no-cache, must-revalidate'); // HTTP/1.1
			header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
			$this->$method();
		}else{
			header('Content-type: text/xml');
			header('Cache-Control: no-cache, must-revalidate'); // HTTP/1.1
			header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
			echo '<?xml version="1.0" encoding="UTF-8"?' . ">\n";
			echo "<AJAXResponse>\n";
			if (is_callable(array($this, $method))) {
				$this->$method();
			} else {
				echo '<Error>Invalid Method</Error>';
			}
			echo '</AJAXResponse>';
		}
	}

	function IsLoggedIn()
	{
		require_once 'services/MyResearch/lib/User.php';

		echo "<result>" .
		(UserAccount::isLoggedIn() ? "True" : "False") . "</result>";
	}

	/**
	 * Support method for getItemStatuses() -- when presented with multiple values,
	 * pick which one(s) to send back via AJAX.
	 *
	 * @access  private
	 * @param   array       $list       Array of values to choose from.
	 * @param   string      $mode       config.ini setting -- first, all or msg
	 * @param   string      $msg        Message to display if $mode == "msg"
	 * @return  string
	 */
	private function pickValue($list, $mode, $msg)
	{
		// Make sure array contains only unique values:
		$list = array_unique($list);

		// If there is only one value in the list, or if we're in "first" mode,
		// send back the first list value:
		if ($mode == 'first' || count($list) == 1) {
			return $list[0];
			// Empty list?  Return a blank string:
		} else if (count($list) == 0) {
			return '';
			// All values mode?  Return comma-separated values:
		} else if ($mode == 'all') {
			return implode(', ', $list);
			// Message mode?  Return the specified message, translated to the
			// appropriate language.
		} else {
			return translate($msg);
		}
	}

	/**
	 * Get Item Statuses
	 *
	 * This is responsible for printing the holdings information for a
	 * collection of records in XML format.
	 *
	 * @access  public
	 * @author  Chris Delis <cedelis@uillinois.edu>
	 */
	function GetItemStatuses()
	{
		global $configArray;

		require_once 'CatalogConnection.php';

		// Try to find a copy that is available
		$catalog = new CatalogConnection($configArray['Catalog']['driver']);

		$result = $catalog->getStatuses($_GET['id']);

		// In order to detect IDs missing from the status response, create an
		// array with a key for every requested ID.  We will clear keys as we
		// encounter IDs in the response -- anything left will be problems that
		// need special handling.
		$missingIds = array_flip($_GET['id']);

		// Loop through all the status information that came back
		foreach ($result as $record) {
			// If we encountered errors, skip those problem records.
			if (PEAR::isError($record)) {
				continue;
			}
			$available = false;
			$location = '';
			$recordId = '';
			$reserve = '';
			$callnumber = '';
			if (count($record)) {
				foreach ($record as $info) {
					if ($recordId == '') $recordId = $info['id'];
					if ($reserve == '') $reserve = $info['reserve'];
					if ($callnumber == '') $callnumber = $info['callnumber'];
					// Find an available copy
					if ($info['availability']) {
						$available = true;
					}

					// Has multiple locations?
					if ($location != 'Multiple Locations') {
						if ($location != '') {
							if ($info['location'] != $location) {
								$location = 'Multiple Locations';
							} else {
								$location = htmlspecialchars($info['location']);
							}
						} else {
							$location = htmlspecialchars($info['location']);
						}
					}
				}

				// The current ID is not missing -- remove it from the missing list.
				unset($missingIds[$recordId]);

				echo ' <item id="' . htmlspecialchars($recordId) . '">';
				if ($available) {
					echo '  <availability>true</availability>';
				} else {
					echo '  <availability>false</availability>';
				}
				echo '  <location>' . htmlspecialchars($location) . '</location>';
				echo '  <reserve>' . htmlspecialchars($reserve) . '</reserve>';
				echo '  <callnumber>' . htmlspecialchars($callnumber) . '</callnumber>';
				echo ' </item>';
			}
		}

		// If any IDs were missing, send back appropriate dummy data
		foreach($missingIds as $missingId => $junk) {
			echo ' <item id="' . htmlspecialchars($missingId) . '">';
			echo '   <availability>false</availability>';
			echo '   <location>Unknown</location>';
			echo '   <reserve>N</reserve>';
			echo '   <callnumber></callnumber>';
			echo ' </item>';
		}
	}

	/**
	 * Get Item Statuses
	 *
	 * This is responsible for getting holding summary information for a list of
	 * records from the database.
	 *
	 * @access  public
	 * @author  Mark Noble <mnoble@turningleaftech.com>
	 */
	function GetStatusSummaries()
	{
		global $configArray;
		global $interface;
		global $timer;

		require_once 'CatalogConnection.php';

		// Try to find a copy that is available
		$catalog = new CatalogConnection($configArray['Catalog']['driver']);
		$timer->logTime("Initialized Catalog Connection");

		$result = $catalog->getStatusSummaries($_GET['id']);
		$timer->logTime("Retrieved status summaries");

		// In order to detect IDs missing from the status response, create an
		// array with a key for every requested ID.  We will clear keys as we
		// encounter IDs in the response -- anything left will be problems that
		// need special handling.
		$missingIds = array_flip($_GET['id']);

		// Loop through all the status information that came back
		foreach ($result as $record) {
			// If we encountered errors, skip those problem records.
			if (PEAR::isError($record)) {
				continue;
			}

			$interface->assign('holdingsSummary', $record);

			$formattedHoldingsSummary = $interface->fetch('Record/holdingsSummary.tpl');

			echo ' <item id="' . htmlspecialchars($record['shortId']) . '">';
			echo '  <id>' . htmlspecialchars($record['shortId']) . '</id>';
			echo '  <status>' . htmlspecialchars($record['status']) . '</status>';
			echo '  <callnumber>' . htmlspecialchars($record['callnumber']) . '</callnumber>';
			echo '  <showplacehold>' . htmlspecialchars($record['showPlaceHold']) . '</showplacehold>';
			echo '  <availablecopies>' . htmlspecialchars($record['availableCopies']) . '</availablecopies>';
			echo '  <holdablecopies>' . htmlspecialchars($record['holdableCopies']) . '</holdablecopies>';
			echo '  <numcopies>' . htmlspecialchars($record['numCopies']) . '</numcopies>';
			echo '  <holdQueueLength>' . (isset($record['holdQueueLength']) ? htmlspecialchars($record['holdQueueLength']) : '') . '</holdQueueLength>';
			echo '  <class>' . htmlspecialchars($record['class']) . '</class>';
			echo '  <isDownloadable>' . ($record['isDownloadable'] ? 1 : 0) . '</isDownloadable>';
			echo '  <downloadLink>' . (isset($record['downloadLink']) ? htmlspecialchars($record['downloadLink']) : '') . '</downloadLink>';
			echo '  <downloadText>' . (isset($record['downloadText']) ? htmlspecialchars($record['downloadText']) : '') . '</downloadText>';
			echo '  <showAvailabilityLine>' . ($record['showAvailabilityLine'] ? 1 : 0) . '</showAvailabilityLine>';
			echo '  <availableAt>' . htmlspecialchars($record['availableAt']) . '</availableAt>';
			echo '  <numAvailableOther>' . $record['numAvailableOther'] . '</numAvailableOther>';
			echo '  <formattedHoldingsSummary>' . htmlspecialchars($formattedHoldingsSummary) . '</formattedHoldingsSummary>';
			echo ' </item>';

		}
		$timer->logTime("Formatted results");
	}
	
	/**
	 * Get Item Statuses
	 *
	 * This is responsible for getting holding summary information for a list of
	 * records from the database.
	 *
	 * @access  public
	 * @author  Mark Noble <mnoble@turningleaftech.com>
	 */
	function GetEContentStatusSummaries()
	{
		global $configArray;
		global $interface;
		global $timer;

		require_once ('Drivers/EContentDriver.php');
		$driver = new EContentDriver();
		//Load status summaries
		$result = $driver->getStatusSummaries($_GET['id']);
				$timer->logTime("Retrieved status summaries");

		// In order to detect IDs missing from the status response, create an
		// array with a key for every requested ID.  We will clear keys as we
		// encounter IDs in the response -- anything left will be problems that
		// need special handling.
		$missingIds = array_flip($_GET['id']);

		// Loop through all the status information that came back
		foreach ($result as $record) {
			// If we encountered errors, skip those problem records.
			if (PEAR::isError($record)) {
				continue;
			}

			$interface->assign('holdingsSummary', $record);

			$formattedHoldingsSummary = $interface->fetch('EContentRecord/holdingsSummary.tpl');

			echo ' <item id="' . htmlspecialchars($record['recordId']) . '">';
			echo '  <status>' . htmlspecialchars($record['status']) . '</status>';
			echo '  <showplacehold>' . ($record['showPlaceHold'] ? '1' : '0') . '</showplacehold>';
			echo '  <showcheckout>' . ($record['showCheckout'] ? '1' : '0') . '</showcheckout>';
			echo '  <showaccessonline>' . ($record['showAccessOnline'] ? '1' : '0') . '</showaccessonline>';
			echo '  <showaddtowishlist>' . ($record['showAddToWishlist'] ? '1' : '0') . '</showaddtowishlist>';
			echo '  <availablecopies>' . htmlspecialchars($record['showAccessOnline']) . '</availablecopies>';
			echo '  <numcopies>' . htmlspecialchars($record['totalCopies']) . '</numcopies>';
			echo '  <holdQueueLength>' . htmlspecialchars($record['holdQueueLength']) . '</holdQueueLength>';
			echo '  <isDownloadable>1</isDownloadable>';
			echo '  <formattedHoldingsSummary>' . htmlspecialchars($formattedHoldingsSummary) . '</formattedHoldingsSummary>';
			echo ' </item>';

		}
		$timer->logTime("Formatted results");
	}

	/**
	 * Get Ratings
	 *
	 * This is responsible for loading rating information for all specified items
	 * from the database.
	 *
	 * Database is returned as json
	 *
	 * @access  public
	 * @author  Mark Noble <mnoble@turningleaftech.com>
	 */
	function GetRatings()
	{
		global $configArray;

		//setup 5 star ratings
		global $user;

		require_once 'services/MyResearch/lib/Resource.php';

		if (isset($_REQUEST['id'])){
			$ids = $_REQUEST['id'];
		}else{
			$ids = array();
		}
		$ratingData = array();
		$ratingData['standard'] = array();
		$ratingData['eContent'] = array();
		foreach ($ids as $id){
			$resource = new Resource();
			$resource->record_id = $id;
			$resource->source = 'VuFind';
			$ratingData['standard'][$id] = $resource->getRatingData($user);
		}
		
		require_once 'sys/eContent/EContentRating.php';
		if (isset($_REQUEST['econtentId'])){
			$econtentIds = $_REQUEST['econtentId'];
		}else{
			$econtentIds = array();
		}
		foreach ($econtentIds as $id){
			$econtentRating = new EContentRating();
			$econtentRating->recordId = $id;
			if ($econtentRating->find()){
				$ratingData['eContent'][$id] = $econtentRating->getRatingData($user, false);
			}
		}
		

		echo json_encode($ratingData);

	}

	function GetSuggestion()
	{
		global $configArray;

		// Setup Search Engine Connection
		$class = $configArray['Index']['engine'];
		$db = new $class($configArray['Index']['url']);

		$query = 'titleStr:"' . $_GET['phrase'] . '*"';
		$result = $db->query($query, 0, 10);

		$resultList = '';
		if (isset($result['record'])) {
			foreach ($result['record'] as $record) {
				if (strlen($record['title']) > 40) {
					$resultList .= htmlspecialchars(substr($record['title'], 0, 40)) . ' ...|';
				} else {
					$resultList .= htmlspecialchars($record['title']) . '|';
				}
			}
			echo '<result>' . $resultList . '</result>';
		}
	}

	// Saves a search to User's Account
	function SaveSearch()
	{
		require_once 'services/MyResearch/lib/User.php';
		require_once 'services/MyResearch/lib/Search.php';

		//check if user is logged in
		if (!($user = UserAccount::isLoggedIn())) {
			echo "<result>Please Log in.</result>";
			return;
		}

		$lookfor = $_GET['lookfor'];
		$limitto = urldecode($_GET['limit']);
		$type = $_GET['type'];

		$search = new SearchEntry();
		$search->user_id = $user->id;
		$search->limitto = $limitto;
		$search->lookfor = $lookfor;
		$search->type = $type;
		if(!$search->find()) {
			$search = new SearchEntry();
			$search->user_id = $user->id;
			$search->lookfor = $lookfor;
			$search->limitto = $limitto;
			$search->type = $type;
			$search->created = date('Y-m-d');

			$search->insert();
		}
		echo "<result>Done</result>";
	}

	// Email Search Results
	function SendEmail()
	{
		require_once 'services/Search/Email.php';

		$emailService = new Email();
		$result = $emailService->sendEmail($_GET['url'], $_GET['to'], $_GET['from'], $_GET['message']);

		if (PEAR::isError($result)) {
			echo '<result>Error</result>';
			echo '<details>' . htmlspecialchars(translate($result->getMessage())) . '</details>';
		} else {
			echo '<result>Done</result>';
		}
	}

	function GetSaveStatus()
	{
		require_once 'services/MyResearch/lib/User.php';
		require_once 'services/MyResearch/lib/Resource.php';

		// check if user is logged in
		if (!($user = UserAccount::isLoggedIn())) {
			echo "<result>Unauthorized</result>";
			return;
		}

		// Check if resource is saved to favorites
		$resource = new Resource();
		$resource->record_id = $_GET['id'];
		if ($resource->find(true)) {
			if ($user->hasResource($resource)) {
				echo '<result>Saved</result>';
			} else {
				echo '<result>Not Saved</result>';
			}
		} else {
			echo '<result>Not Saved</result>';
		}
	}

	/**
	 * Get Save Statuses
	 *
	 * This is responsible for printing the save status for a collection of
	 * records in XML format.
	 *
	 * @access  public
	 * @author  Chris Delis <cedelis@uillinois.edu>
	 */
	function GetSaveStatuses()
	{
		require_once 'services/MyResearch/lib/User.php';
		require_once 'services/MyResearch/lib/Resource.php';
		global $configArray;

		// check if user is logged in
		if (!($user = UserAccount::isLoggedIn())) {
			echo "<result>Unauthorized</result>";
			return;
		}

		for ($i=0; ; $i++) {
			if (! isset($_GET['id' . $i])) break;
			$id = $_GET['id' . $i];
			echo '<item id="' . htmlspecialchars($id) . '">';

			// Check if resource is saved to favorites
			$resource = new Resource();
			$resource->record_id = $id;
			if ($resource->find(true)) {
				$data = $user->getSavedData($id, $resource->source);
				if ($data) {
					echo '<result>';
					// Convert the resource list into JSON so it's easily readable
					// by the calling Javascript code.  Note that we have to entity
					// encode it so it can embed cleanly inside our XML response.
					$json = array();
					foreach ($data as $list) {
						$listData = new User_list();
						$listData->id = $list->list_id;
						if ($listData->find(true)){
							if ($listData->user_id == $user->id || $listData->public){
								$link = $configArray['Site']['path'] . '/MyResearch/MyList/' . $listData->id;
							}else{
								$link ='';
							}
						}
						$json[] = array('id' => $list->id, 'title' => $list->list_title, 'link' => $link);
					}
					echo htmlspecialchars(json_encode($json));
					echo '</result>';
				} else {
					echo '<result>False</result>';
				}
			} else {
				echo '<result>False</result>';
			}

			echo '</item>';
		}
	}

	function GetSavedData()
	{
		require_once 'services/MyResearch/lib/User.php';
		require_once 'services/MyResearch/lib/Resource.php';

		// check if user is logged in
		if ((!$user = UserAccount::isLoggedIn())) {
			echo "<result>Unauthorized</result>";
			return;
		}

		echo "<result>\n";

		$saved = $user->getSavedData($_GET['id']);
		if ($saved->notes) {
			echo "  <Notes>$saved->notes</Notes>\n";
		}

		$myTagList = $user->getTags($_GET['id']);
		if (count($myTagList)) {
			foreach ($myTagList as $tag) {
				echo "  <Tag>" . $tag->tag . "</Tag>\n";
			}
		}

		echo '</result>';
	}



	function GetAutoSuggestList(){
		require_once 'services/Search/lib/SearchSuggestions.php';
		global $timer;

		$searchTerm = isset($_REQUEST['searchTerm']) ? $_REQUEST['searchTerm'] : $_REQUEST['q'];
		$searchType = isset($_REQUEST['type']) ? $_REQUEST['type'] : '';
		$suggestions = new SearchSuggestions();
		$commonSearches = $suggestions->getAllSuggestions($searchTerm, $searchType);
		$timer->logTime("loaded search suggestions");
		if (count($commonSearches) > 0){
			echo json_encode($commonSearches);
		}
		$timer->logTime("rendered search suggestions");
	}

	function getProspectorResults(){
		$prospectorNumTitlesToLoad = $_GET['prospectorNumTitlesToLoad'];
		$prospectorSavedSearchId = $_GET['prospectorSavedSearchId'];

		require_once 'Drivers/marmot_inc/Prospector.php';
		global $configArray;
		global $interface;
		global $timer;

		$searchObject = SearchObjectFactory::initSearchObject();
		$searchObject->init();
		// Setup Search Engine Connection
		$class = $configArray['Index']['engine'];
		$url = $configArray['Index']['url'];
		$db = new $class($url);
		if ($configArray['System']['debugSolr']) {
			$db->debug = true;
		}
		$searchObject = $searchObject->restoreSavedSearch($prospectorSavedSearchId, false);

		//Load results from Prospector
		$prospector = new Prospector();
		$prospectorResults = $prospector->getTopSearchResults($searchObject->getSearchTerms(), $prospectorNumTitlesToLoad);
		$interface->assign('prospectorResults', $prospectorResults);
		$prospectorLink = $prospector->getSearchLink($searchObject->getSearchTerms());
		$interface->assign('prospectorLink', $prospectorLink);
		$timer->logTime('load Prospector titles');
		echo $interface->fetch('Search/ajax-prospector.tpl');
	}

	function RandomSysListTitles(){
		global $timer;
		require_once('sys/Cache/ListCache2.php');
		$listName = $_GET['name'];
		$scrollerName = $_GET['scrollerName'];

		//Check to see if the list has been cached and is recent
		$listCache = new ListCache2();
		$listCache->listName = $listName;
		$listCache->scrollerName = $scrollerName;
		if ($listCache->find(true)){
			echo $listCache->jsonData;
			$timer->logTime("Loaded cached ListCache2 for $listName");
			return;
		}
		$timer->logTime("Finished checking for cached ListCache2 for $listName");

		require_once('services/API/ListAPI.php');
		global $interface;
		global $configArray;

		$listAPI = new ListAPI();

		$titles = $listAPI->getRandomSystemListTitles($listName);
		$timer->logTime("Got titles from list api for $listName");

		foreach ($titles as $key => $rawData){
				
			$interface->assign('description', $rawData['description']);
			$interface->assign('length', $rawData['length']);
			$interface->assign('publisher', $rawData['publisher']);
			$descriptionInfo = $interface->fetch('Record/ajax-description-popup.tpl') ;
				
			$formattedTitle = "<div id=\"scrollerTitle{$scrollerName}{$key}\" class=\"scrollerTitle\">" .
    			'<a href="' . $configArray['Site']['path'] . "/Record/" . $rawData['id'] . '" id="descriptionTrigger' . $rawData['id'] . '">' . 
    			"<img src=\"{$rawData['image']}\" class=\"scrollerTitleCover\" alt=\"{$rawData['title']} Cover\"/>" . 
    			"</a></div>" . 
    			"<div id='descriptionPlaceholder{$rawData['id']}' style='display:none' class='loaded'>" .
			$descriptionInfo .
    			"</div>";
			$rawData['formattedTitle'] = $formattedTitle;
			$titles[$key] = $rawData;
		}
		$timer->logTime("Formatted titles for list $listName");
		$return = array('titles' => $titles, 'currentIndex' => 15);
		$json_data = json_encode($return);
		$listCache->jsonData = $json_data;
		$listCache->cacheDate = time();
		$listCache->insert();
		$timer->logTime("Updated listcache2 for $listName");
		echo ($json_data);
	}

	/**
	 * For historical purposes.  Make sure the old API wll still work.
	 */
	function SysListTitles(){
		if (!isset($_GET['id'])){
			$_GET['id'] = $_GET['name'];
		}
		return GetListTitles();
	}
	function GetListTitles(){
		require_once('sys/Cache/ListCache2.php');
		$listName = isset($_GET['scrollerName']) ? $_GET['scrollerName'] : 'List' . $_GET['id'];
		$scrollerName = $_GET['scrollerName'];
		//Check to see if the list has been cached and is recent
		$listCache = new ListCache2();
		$listCache->listName = $listName;
		$listCache->scrollerName = $scrollerName;
		if ($listCache->find(true)){
			if (isset($_REQUEST['reload'])){
				$listCache->delete();
			}else{
				echo $listCache->jsonData;
				return;
			}
		}

		require_once('services/API/ListAPI.php');
		global $interface;
		global $configArray;
		$listAPI = new ListAPI();

		$titles = $listAPI->getListTitles();
		$addStrandsTracking = false;
		if ($titles['success'] == true){
			if (isset($titles['strands'])){
				$addStrandsTracking = true;
				$strandsInfo = $titles['strands'];
			}
			$titles = $titles['titles'];
		}else{
			$return = array('titles' => array(), 'currentIndex' =>0);
			return $return;
		}

		
		foreach ($titles as $key => $rawData){
			 
			$interface->assign('description', $rawData['description']);
			$interface->assign('length', $rawData['length']);
			$interface->assign('publisher', $rawData['publisher']);
			$descriptionInfo = $interface->fetch('Record/ajax-description-popup.tpl') ;
			 
			$formattedTitle = "<div id=\"scrollerTitle{$scrollerName}{$key}\" class=\"scrollerTitle\">" .
          '<a href="' . $configArray['Site']['path'] . "/Record/" . $rawData['id'] . ($addStrandsTracking ? "?strandsReqId={$strandsInfo['reqId']}&strandsTpl={$strandsInfo['tpl']}" : '') . '" id="descriptionTrigger' . $rawData['id'] . '">' . 
          "<img src=\"{$rawData['image']}\" class=\"scrollerTitleCover\" alt=\"{$rawData['title']} Cover\"/>" . 
          "</a></div>" . 
          "<div id='descriptionPlaceholder{$rawData['id']}' style='display:none' class='loaded'>" .
			$descriptionInfo .
          "</div>";
			$rawData['formattedTitle'] = $formattedTitle;
			$titles[$key] = $rawData;
		}
		$currentIndex = count($titles) > 5 ? floor(count($titles) / 2) : 0;
		 
		$return = array('titles' => $titles, 'currentIndex' => $currentIndex);
		$json_data = json_encode($return);
		if (isset($return['cacheable']) && $return['cacheable'] == true){
			$listCache->jsonData = $json_data;
			$listCache->cacheDate = time();
			$listCache->insert();
		}
		echo ($json_data);
	}
}

function ar2xml($ar)
{
	$doc = new DOMDocument('1.0', 'utf-8');
	$doc->formatOutput = true;
	foreach ($ar as $facet => $value) {
		$element = $doc->createElement($facet);
		foreach ($value as $term => $cnt) {
			$child = $doc->createElement('term', $term);
			$child->setAttribute('count', $cnt);
			$element->appendChild($child);
		}
		$doc->appendChild($element);
	}

	return strstr($doc->saveXML(), "\n");
}