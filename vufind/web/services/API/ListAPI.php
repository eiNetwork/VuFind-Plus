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
require_once 'sys/SolrStats.php';
require_once 'sys/Pager.php';
require_once 'services/MyResearch/lib/User_list.php';

class ListAPI extends Action {

	function launch() {
		$method = $_GET['method'];
		if ($method == 'getRSSFeed'){
			header ('Content-type: text/xml');
			header('Cache-Control: no-cache, must-revalidate'); // HTTP/1.1
			header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
			$xml = '<?xml version="1.0" encoding="UTF-8"?' . ">\n";
			if (is_callable(array($this, $_GET['method']))) {
				$xml .= $this->$_GET['method']();
			} else {
				$xml .= '<Error>Invalid Method</Error>';
			}
			 
			echo $xml;
		  
		}else{
			header('Content-type: text/plain');
			header('Cache-Control: no-cache, must-revalidate'); // HTTP/1.1
			header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
			if (is_callable(array($this, $_GET['method']))) {
				$output = json_encode(array('result'=>$this->$_GET['method']()));
			} else {
				$output = json_encode(array('error'=>'invalid_method'));
			}

			echo $output;
		}
	}

	function getAllListIds(){
		$allListNames = array();
		$publicLists = $this->getPublicLists();
		if ($publicLists['success'] = true){
			foreach ($publicLists['lists'] as $listInfo){
				$allListNames[] = $listInfo['id'];
			}
		}
		$systemLists = $this->getSystemLists();
		if ($systemLists['success'] = true){
			foreach ($systemLists['lists'] as $listInfo){
				$allListNames[] = $listInfo['id'];
			}
		}
		return $allListNames;
	}

	/**
	 * Get all public lists
	 * includes id, title, description, and number of titles
	 */
	function getPublicLists(){
		$list = new User_list();
		$list->public = 1;
		$list->find();
		$results = array();
		if ($list->N > 0){
			while ($list->fetch()){
				$query = "SELECT count(resource_id) as numTitles FROM user_resource where list_id = {$list->id}";
				$numTitleResults = mysql_query($query);
				$numTitles = mysql_fetch_assoc($numTitleResults);

				$results[] = array(
				  'id' => $list->id,
          'title' => $list->title,
				  'description' => $list->description,
				  'numTitles' => $numTitles['numTitles'],
				);
			}
		}
		return array('success'=>true, 'lists'=>$results);
	}

	/**
	 * Get all lists that a particular user has created.
	 * includes id, title, description, number of titles, and whether or not the list is public
	 */
	function getUserLists(){
		$username = $_REQUEST['username'];
		$password = $_REQUEST['password'];
		global $user;
		$user = UserAccount::validateAccount($username, $password);
		if (!isset($_REQUEST['username']) || !isset($_REQUEST['password'])){
			return array('success'=>false, 'message'=>'The username and password must be provided to load lists.');
		}

		$userId = $user->id;

		$list = new User_list();
		$list->user_id = $userId;
		$list->find();
		$results = array();
		if ($list->N > 0){
			while ($list->fetch()){
				$query = "SELECT count(resource_id) as numTitles FROM user_resource where list_id = {$list->id}";
				$numTitleResults = mysql_query($query);
				$numTitles = mysql_fetch_assoc($numTitleResults);

				$results[] = array(
          'id' => $list->id,
          'title' => $list->title,
          'description' => $list->description,
          'numTitles' => $numTitles['numTitles'],
          'public' => $list->public == 1,
				);
			}
		}
		require_once('services/MyResearch/lib/Suggestions.php');
		$suggestions = Suggestions::getSuggestions($userId);
		if (count($suggestions) > 0){
			$results[] = array(
          'id' => 'recommendations',
          'title' => 'User Recommendations',
          'description' => 'Personalized Recommendations based on ratings.',
          'numTitles' => count($suggestions),
          'public' => false,
			);
		}
		return array('success'=>true, 'lists'=>$results);
	}

	/**
	 * Get's RSS Feed
	 */
	function getRSSFeed(){
		global $configArray;

		$rssfeed = '<rss version="2.0">';
		$rssfeed .= '<channel>';

		if (!isset($_REQUEST['id'])){
			$rssfeed .= '<error>No ID Provided</error>';
		}else{
			$listId = $_REQUEST['id'];
			$curDate = date("D M j G:i:s T Y");

			//Grab the title based on the list that id that is passed in
			$titleData = array();
			$titleData = $this->getListTitles($listId);
			$titleCount = count($titleData["titles"]);
				
			if ($titleCount > 0){
				
				$listTitle = $titleData["listTitle"];
				$listDesc = $titleData["listDescription"];
								
				$rssfeed .= '<title>'. $listTitle .'</title>';
				$rssfeed .= '<language>en-us</language>';
				$rssfeed .= '<description>'. $listDesc .'</description>';
				$rssfeed .= '<lastBuildDate>'. $curDate .'</lastBuildDate>';
				$rssfeed .= '<pubDate>'. $curDate .'</pubDate>';
				$rssfeed .= '<link>' . htmlspecialchars($configArray['Site']['url'] . '/API/ListAPI?method=getRSSFeed&id=' . $listId) . '</link>';

				foreach($titleData["titles"] as $title){
					$titleId = $title["id"];
					$image = $title["image"];
					$bookTitle = $title["title"];
					$bookTitle = rtrim($bookTitle, " /");
					$author = $title["author"];
					$description = $title["description"];
					$length = $title["length"];
					$publisher = $title["publisher"];
					
					if (isset($title["dateSaved"])) {
						$pubDate = $title["dateSaved"];
					} else {
						$pubDate = "No Date Available";
					}
				
					 
					$rssfeed .= '<item>';
					$rssfeed .= '<id>' . $titleId . '</id>';
					$rssfeed .= '<image>' . htmlspecialchars($image) . '</image>';
					$rssfeed .= '<title>' . htmlspecialchars($bookTitle) . '</title>';
					$rssfeed .= '<author>' . htmlspecialchars($author) . '</author>';
					$itemLink;
					if ($title['recordtype'] == 'econtentRecord'){
						$titleIdShort = preg_replace('/econtentRecord/', '', $titleId);
						$itemLink = htmlspecialchars($configArray['Site']['url'] . '/EContentRecord/' . $titleIdShort);
					}else{
						$itemLink = htmlspecialchars($configArray['Site']['url'] . '/Record/' . $titleId);
					}
					
					$fullDescription = "<a href='$itemLink'><img src='$image' alt='cover'/></a>$description";
					$rssfeed .= '<description>' . htmlspecialchars($fullDescription) . '</description>';
					$rssfeed .= '<length>' . $length . '</length>';
					$rssfeed .= '<publisher>' . htmlspecialchars($publisher) . '</publisher>';
					$rssfeed .= '<pubDate>' . $pubDate . '</pubDate>';
					if ($title['recordtype'] == 'econtentRecord'){
						$titleIdShort = preg_replace('/econtentRecord/', '', $titleId);
						$rssfeed .= '<link>' . $itemLink . '</link>';
					}else{
						$rssfeed .= '<link>' . $itemLink . '</link>';
					}
					//$rssfeed .= '<pubDate>' . date("D, d M Y H:i:s O", strtotime($date)) . '</pubDate>';

					$rssfeed .= '</item>';
					 
				}
			} else {
				$rssfeed .= '<error>No Titles Listed</error>';
			}
				
		}

		$rssfeed .= '</channel>';
		$rssfeed .= '</rss>';
		
			
		return $rssfeed;
	}

	/**
	 * Get all system generated lists that are available.
	 * includes id, title, description, and number of titles
	 */
	function getSystemLists(){
		//System lists are not stored in tables, but are generated based on
		//a variety of factors.
		$systemLists[] = array(
      'id' => 'newfic',
		  'title' => 'New Fiction',
		  'description' => 'A selection of New Fiction Titles that have arrived recently or are on order.',
		  'numTitles' => 30,
		);
		$systemLists[] = array(
      'id' => 'newnonfic',
      'title' => 'New Non-Fiction',
      'description' => 'A selection of New Non-Fiction Titles that have arrived recently or are on order.',
      'numTitles' => 30,
		);
		$systemLists[] = array(
      'id' => 'newdvd',
      'title' => 'New DVDs',
      'description' => 'A selection of New DVDs that have arrived recently or are on order.',
      'numTitles' => 30,
		);
		$systemLists[] = array(
      'id' => 'newmyst',
      'title' => 'New Mysteries',
      'description' => 'A selection of New Mystery Books that have arrived recently or are on order.',
      'numTitles' => 30,
		);
		$systemLists[] = array(
      'id' => 'newaudio',
      'title' => 'New Audio',
      'description' => 'A selection of New Audio Books that have arrived recently or are on order.',
      'numTitles' => 30,
		);
		$systemLists[] = array(
      'id' => 'newya',
      'title' => 'New Young Adult',
      'description' => 'A selection of New Titles appropriate for Young Adult that have arrived recently or are on order.',
      'numTitles' => 30,
		);
		$systemLists[] = array(
      'id' => 'newkids',
      'title' => 'New Kids',
      'description' => 'A selection of New Titles appropriate for children that have arrived recently or are on order.',
      'numTitles' => 30,
		);
		$systemLists[] = array(
      'id' => 'newEpub',
      'title' => 'New Online Books',
      'description' => 'The most recently added online books in the catalog.',
      'numTitles' => 30,
		);
		$systemLists[] = array(
      'id' => 'newebooks',
      'title' => 'New eBooks',
      'description' => 'The most recently added online books in the catalog.',
      'numTitles' => 30,
		);
		$systemLists[] = array(
      'id' => 'comingsoonfic',
      'title' => 'Coming Soon Fiction',
      'description' => 'A selection of Fiction Titles that are on order and due in soon.',
      'numTitles' => 30,
		);
		$systemLists[] = array(
      'id' => 'comingsoonnonfic',
      'title' => 'Coming Soon Non-Fiction',
      'description' => 'A selection of Non-Fiction Titles that are on order and due in soon.',
      'numTitles' => 30,
		);
		$systemLists[] = array(
      'id' => 'comingsoondvd',
      'title' => 'Coming Soon DVDs',
      'description' => 'A selection of DVDs that are on order and due in soon.',
      'numTitles' => 30,
		);
		$systemLists[] = array(
      'id' => 'comingsoonkids',
      'title' => 'Coming Soon Kids',
      'description' => 'A selection of Kids Titles that are on order and due in soon.',
      'numTitles' => 30,
		);
		$systemLists[] = array(
      'id' => 'comingsoonya',
      'title' => 'Coming Soon Young Adult',
      'description' => 'A selection of Young Adult Titles that are on order and due in soon.',
      'numTitles' => 30,
		);
		$systemLists[] = array(
      'id' => 'comingsoonmusic',
      'title' => 'Coming Soon Music',
      'description' => 'A selection of Music Tiles that are on order and due in soon.',
      'numTitles' => 30,
		);
		/*$systemLists[] = array(
		 'id' => 'popularEpub',
		 'title' => 'Popular Online Books',
		 'description' => 'The most popular books that are available to read online.',
		 'numTitles' => 30,
		 );
		 $systemLists[] = array(
		 'id' => 'availableEpub',
		 'title' => 'Available Online Books',
		 'description' => 'Online books that can be read immediately.',
		 'numTitles' => 30,
		 );
		 $systemLists[] = array(
		 'id' => 'recommendedEpub',
		 'title' => 'Recommended Online Books',
		 'description' => 'Online books that you may like based on your ratings and reading history.',
		 'numTitles' => 30,
		 );*/
		$systemLists[] = array(
      'id' => 'recentlyReviewed',
      'title' => 'Recently Reviewed',
      'description' => 'Titles that have had new reviews added to them.',
      'numTitles' => 30,
		);
		$systemLists[] = array(
      'id' => 'highestRated',
      'title' => 'Highly Rated',
      'description' => 'Titles that have the highest ratings within the catalog.',
      'numTitles' => 30,
		);
		$systemLists[] = array(
      'id' => 'mostPopular',
      'title' => 'Most Popular Titles',
      'description' => 'Most Popular titles based on checkout history.',
      'numTitles' => 30,
		);
		return array('success'=>true, 'lists'=>$systemLists);
	}
	
	/**
	 * Returns information about the titles within a list including:
	 * - Title, Author, Bookcover URL, description, record id
	 */
	function getListTitles() {
		global $interface;
		global $configArray;
		global $timer;

		if (!isset($_REQUEST['id'])){
			return array('success'=>false, 'message'=>'The id of the list to load must be provided as the id parameter.');
		}

		if (isset($_REQUEST['username']) && isset($_REQUEST['password'])){
			$username = $_REQUEST['username'];
			$password = $_REQUEST['password'];
			global $user;
			$user = UserAccount::validateAccount($username, $password);
		}else{
			global $user;
		}

		if ($user){
			$userId = $user->id;
		}

		$listId = $_REQUEST['id'];
		if (is_numeric($listId)){
			//The list is a patron generated list
			$list = new User_list();
			$list->id = $listId;
			if ($list->find(true)){
				//Make sure the user has acess to the list
				if ($list->public == 0){
					if (!$user){
						return array('success'=>false, 'message'=>'The user was invalid.  A valid user must be provided for private lists.');
					}elseif ($list->user_id != $userId){
						return array('success'=>false, 'message'=>'The user does not have access to this list.');
					}
				}
				//Load the titles for the list.
				$listResources = $list->getResources();

				$ids = array();
				$datesSaved = array();
				foreach ($listResources as $resource){
					$ids[] = $resource->record_id;
					$datesSaved[$resource->record_id] = $resource->saved;
				}
				$titles = $this->loadTitleInformationForIds($ids, array(), $datesSaved);
				return array('success' => true, 'listName' => $list->title, 'listDescription' => $list->description, 'titles'=>$titles, 'cacheable'=>true);
			}else{
				return array('success'=>false, 'message'=>'The specified list could not be found.');
			}
		}elseif (preg_match('/strands:(.*)/', $listId, $strandsInfo)){
			//Load the data from strands
			$strandsTemplate = $strandsInfo[1];
			$recordId = isset($_REQUEST['recordId']) ? $_REQUEST['recordId'] : '';
			$userId = $user ? $user->id : '';
			$strandsUrl = "http://bizsolutions.strands.com/api2/recs/item/get.sbs?apid={$configArray['Strands']['APID']}&user={$userId}&tpl={$strandsTemplate}&format=json&amount=25&metadata=false&explanation=true&item={$recordId}";
			$results = json_decode(file_get_contents($strandsUrl));
			$ids = array();
			foreach ($results->result->recommendations as $recommendation){
				$ids[] = $recommendation->itemId;
			}
			$titles = $this->loadTitleInformationForIds($ids);
			return array('success' => true, 'listName' => $strandsTemplate, 'listDescription' => 'Strands recommendations', 'titles'=>$titles, 'cacheable'=>false, 'strands' => array('reqId' => $results->result->reqId, 'tpl' => $results->result->tpl));
		}elseif (preg_match('/review:(.*)/', $listId, $reviewInfo)){
			require_once '/services/MyResearch/lib/Comments.php';
			require_once '/services/MyResearch/lib/User_resource.php';
			//Load the data from strands
			$reviewTag = $reviewInfo[1];
			//Load a list of reviews based on the tag
			$comments = new Comments();
			$comments->whereAdd("comment like \"%#" . mysql_escape_string($reviewTag) . '"');
			$comments->find();
			$recordIds = array();
			$reviews = array();
			$datesSaved = array();
			while ($comments->fetch()){
				$resourceId = $comments->resource_id;
				//Load the resource from the database
				$resource = new Resource();
				$resource->id = $comments->resource_id;
				$resource->find(true);
				$recordIds[$resource->record_id] = $resource->record_id;
				$comment = preg_replace('/#.*/', '', $comments->comment);
				$reviews[$resource->record_id] = $comment;
				$datesSaved[$resource->record_id] = $comments->created;
			}
			$titles = $this->loadTitleInformationForIds($recordIds, $reviews, $datesSaved);
			return array('success' => true, 'listName' => $reviewTag, 'listDescription' => 'Tagged reviews', 'titles'=>$titles, 'cacheable'=>false);
		}else{
			$systemList = null;
			$systemLists = $this->getSystemLists();
			foreach ($systemLists['lists'] as $curSystemList){
				if ($curSystemList['id'] == $listId){
					$systemList = $curSystemList;
					break;
				}
			}
			//The list is a system generated list
			if ($listId == 'newfic'){
				$titles = $this->getRandomSystemListTitles('New Fiction');
				return array('success'=>true, 'listTitle' => $systemList['title'], 'listDescription' => $systemList['description'], 'titles'=>$titles);
			}elseif ($listId == 'newnonfic'){
				$titles = $this->getRandomSystemListTitles('New Non-Fiction');
				return array('success'=>true, 'listTitle' => $systemList['title'], 'listDescription' => $systemList['description'], 'titles'=>$titles);
			}elseif ($listId == 'newdvd'){
				$titles = $this->getRandomSystemListTitles('New DVDs');
				return array('success'=>true, 'listTitle' => $systemList['title'], 'listDescription' => $systemList['description'], 'titles'=>$titles);
			}elseif ($listId == 'newmyst'){
				$titles = $this->getRandomSystemListTitles('New Mysteries');
				return array('success'=>true, 'listTitle' => $systemList['title'], 'listDescription' => $systemList['description'], 'titles'=>$titles);
			}elseif ($listId == 'newaudio'){
				$titles = $this->getRandomSystemListTitles('New Audio');
				return array('success'=>true, 'listTitle' => $systemList['title'], 'listDescription' => $systemList['description'], 'titles'=>$titles);
			}elseif ($listId == 'newya'){
				$titles = $this->getRandomSystemListTitles('New Young Adult');
				return array('success'=>true, 'listTitle' => $systemList['title'], 'listDescription' => $systemList['description'], 'titles'=>$titles);
			}elseif ($listId == 'newkids'){
				$titles = $this->getRandomSystemListTitles('New Kids');
				return array('success'=>true, 'listTitle' => $systemList['title'], 'listDescription' => $systemList['description'], 'titles'=>$titles);
			}elseif ($listId == 'comingsoonfic'){
				$titles = $this->getRandomSystemListTitles('Coming Soon Fiction');
				return array('success'=>true, 'listTitle' => $systemList['title'], 'listDescription' => $systemList['description'], 'titles'=>$titles);
			}elseif ($listId == 'comingsoonnonfic'){
				$titles = $this->getRandomSystemListTitles('Coming Soon Non-Fiction');
				return array('success'=>true, 'listTitle' => $systemList['title'], 'listDescription' => $systemList['description'], 'titles'=>$titles);
			}elseif ($listId == 'comingsoondvd'){
				$titles = $this->getRandomSystemListTitles('Coming Soon DVDs');
				return array('success'=>true, 'listTitle' => $systemList['title'], 'listDescription' => $systemList['description'], 'titles'=>$titles);
			}elseif ($listId == 'comingsoonkids'){
				$titles = $this->getRandomSystemListTitles('Coming Soon Kids');
				return array('success'=>true, 'listTitle' => $systemList['title'], 'listDescription' => $systemList['description'], 'titles'=>$titles);
			}elseif ($listId == 'comingsoonya'){
				$titles = $this->getRandomSystemListTitles('Coming Soon Young Adult');
				return array('success'=>true, 'listTitle' => $systemList['title'], 'listDescription' => $systemList['description'], 'titles'=>$titles);
			}elseif ($listId == 'comingsoonmusic'){
				$titles = $this->getRandomSystemListTitles('Coming Soon Music');
				return array('success'=>true, 'listTitle' => $systemList['title'], 'listDescription' => $systemList['description'], 'titles'=>$titles);
			}elseif ($listId == 'newEpub' || $listId == 'newebooks'){
				require_once ('sys/eContent/EContentRecord.php');
				$eContentRecord = new EContentRecord;
				$eContentRecord->orderBy('date_added DESC');
				$eContentRecord->limit(0, 30);
				$eContentRecord->find();
				$titles = array();
				while($eContentRecord->fetch()){
					$titles[] = array(
            'id' => $eContentRecord->id,
            'image' => $configArray['Site']['coverUrl'] . "/bookcover.php?id=" . $eContentRecord->id . "&isn=" . $eContentRecord->getIsbn() . "&size=medium&upc=" . $eContentRecord->getUpc() . "&category=EMedia&econtent=true",
            'large_image' => $configArray['Site']['coverUrl'] . "/bookcover.php?id=" . $eContentRecord->id . "&isn=" . $eContentRecord->getIsbn() . "&size=large&upc=" . $eContentRecord->getUpc() . "&category=EMedia&econtent=true",
            'title' => $eContentRecord->title,
            'author' => $eContentRecord->author,
				    'description' => $eContentRecord->description,
	          'length' => '',
	          'publisher' => $eContentRecord->publisher,
						'dateSaved' => $eContentRecord->date_added,
					);
				}
				return array('success'=>true, 'listTitle' => $systemList['title'], 'listDescription' => $systemList['description'], 'titles'=>$titles, 'cacheable'=>true);
			}elseif ($listId == 'highestRated'){
				$query = "SELECT record_id, AVG(rating) FROM `user_rating` inner join resource on resourceid = resource.id GROUP BY resourceId order by AVG(rating) DESC LIMIT 30";
				$result = mysql_query($query);
				$ids = array();
				while ($epubInfo = mysql_fetch_assoc($result)){
					$ids[] = $epubInfo['record_id'];
				}
				$titles = $this->loadTitleInformationForIds($ids);
				return array('success'=>true, 'listTitle' => $systemList['title'], 'listDescription' => $systemList['description'], 'titles'=>$titles, 'cacheable'=>true);
			}elseif ($listId == 'recentlyReviewed'){
				$query = "SELECT record_id, MAX(created) FROM `comments` inner join resource on resource_id = resource.id group by resource_id order by max(created) DESC LIMIT 30";
				$result = mysql_query($query);
				$ids = array();
				while ($epubInfo = mysql_fetch_assoc($result)){
					$ids[] = $epubInfo['record_id'];
				}
				$titles = $this->loadTitleInformationForIds($ids);
				return array('success'=>true, 'listTitle' => $systemList['title'], 'listDescription' => $systemList['description'], 'titles'=>$titles, 'cacheable'=>false);
			}elseif ($listId == 'mostPopular'){
				$query = "SELECT record_id, count(userId) from user_reading_history inner join resource on resourceId = resource.id GROUP BY resourceId order by count(userId) DESC LIMIT 30";
				$result = mysql_query($query);
				$ids = array();
				while ($epubInfo = mysql_fetch_assoc($result)){
					$ids[] = $epubInfo['record_id'];
				}
				$titles = $this->loadTitleInformationForIds($ids);
				return array('success'=>true, 'listTitle' => $systemList['title'], 'listDescription' => $systemList['description'], 'titles'=>$titles, 'cacheable'=>true);
			}elseif ($listId == 'recommendations'){
				if (!$user){
					return array('success'=>false, 'message'=>'A valid user must be provided to load recommendations.');
				}
				require_once('services/MyResearch/lib/Suggestions.php');
				$suggestions = Suggestions::getSuggestions($userId);
				$titles = array();
				foreach ($suggestions as $id=>$suggestion){
					$titles[] = array(
            'id' => $id,
            'image' => $configArray['Site']['coverUrl'] . "/bookcover.php?id=" . $id . "&isn=" . $suggestion['titleInfo']['isbn10'] . "&size=medium&upc=" . $suggestion['titleInfo']['upc'] . "&category=" . $suggestion['titleInfo']['format_category'][0],
            'title' => $suggestion['titleInfo']['title'],
            'author' => $suggestion['titleInfo']['author']
					);
				}
				return array('success'=>true, 'listTitle' => $systemList['title'], 'listDescription' => $systemList['description'], 'titles'=>$titles, 'cacheable'=>false);
			}else{
				$titles = $this->getRandomSystemListTitles($listId);
				if (count($titles) > 0 ){
					return array('success'=>true, 'listTitle' => $listId, 'listDescription' => "System Generated List", 'titles'=>$titles, 'cacheable'=>true);
				}else{
					return array('success'=>false, 'message'=>'The specified list could not be found.');
				}
			}
		}

	}

	function loadTitleInformationForIds($ids, $descriptions = array(), $datesSaved = array()){
		require_once('services/Record/Description.php');

		global $configArray;
		$searchObject = SearchObjectFactory::initSearchObject();
		$searchObject->init();
		$titles = array();
		if (count($ids) > 0){
			$searchObject->setQueryIDs($ids);
			$result = $searchObject->processSearch();
			$matchingRecords = $searchObject->getResultRecordSet();
			foreach ($matchingRecords as $record){
				if (isset($record['isbn'])){
					$isbn = $record['isbn'][0];
					if (strpos($isbn, ' ') > 0){
						$isbn = substr($isbn, 0, strpos($isbn, ' '));
					}
				}else{
					$isbn = '';
				}

				// Process MARC Data
				$marc = trim($record['fullrecord']);
				$marc = preg_replace('/#31;/', "\x1F", $marc);
				$marc = preg_replace('/#30;/', "\x1E", $marc);
				$marc = new File_MARC($marc, File_MARC::SOURCE_STRING);
				if ($marcRecord = $marc->next()) {
					$descriptiveInfo = Description::loadDescriptionFromMarc($marcRecord);
				}

				if (isset($descriptions) && isset($descriptions[$record['id']])){
					$descriptiveInfo['description'] = $descriptions[$record['id']];
				}

					
				$titles[] = array(
            'id' => $record['id'],
            'image' => $configArray['Site']['coverUrl'] . "/bookcover.php?id=" . $record['id'] . "&isn=" . $isbn . "&size=medium&upc=" . (isset($record['upc']) ? $record['upc'][0] : '') . "&category=" . $record['format_category'][0],
            'large_image' => $configArray['Site']['coverUrl'] . "/bookcover.php?id=" . $record['id'] . "&isn=" . $isbn . "&size=large&upc=" . (isset($record['upc']) ? $record['upc'][0] : '') . "&category=" . $record['format_category'][0],
            'title' => $record['title'],
            'author' => isset($record['author']) ? $record['author'] : '',
				    'description' => $descriptiveInfo['description'],
	          'length' => $descriptiveInfo['length'],
	          'publisher' => $descriptiveInfo['publisher'],
						'dateSaved' => isset($datesSaved[$record['id']]) ? $datesSaved[$record['id']] : '',

				);
			}
		}
		return $titles;
	}

	function getRandomSystemListTitles($listName){
		require_once('sys/Cache/ListCache.php');
		//Check to see if the list has been cached and is recent
		$listCache = new ListCache();
		$listCache->listName = $listName;
		if ($listCache->find(true)){
			return json_decode($listCache->jsonData, true);
		}

		global $configArray;
		require_once('services/Record/Description.php');
		//return a random selection of 30 titles from the list.
		$scrollerName = $_GET['scrollerName'];
		$searchObj = SearchObjectFactory::initSearchObject();
		$searchObj->init();
		$searchObj->setBasicQuery("*:*");
		$searchObj->addFilter("system_list:$listName");
		$seed = rand(0, 1000);
		$searchObj->setSort("random" . $seed);
		$searchObj->setLimit(50);
		$searchObj->processSearch(false, false);
		$matchingRecords = $searchObj->getResultRecordSet();

		$titles = array();
		foreach ($matchingRecords as $record){
			$isbn = $record['isbn'][0];
			if (strpos($isbn, ' ') > 0){
				$isbn = substr($isbn, 0, strpos($isbn, ' '));
			}

			// Process MARC Data
			$marc = trim($record['fullrecord']);
			$marc = preg_replace('/#31;/', "\x1F", $marc);
			$marc = preg_replace('/#30;/', "\x1E", $marc);
			$marc = new File_MARC($marc, File_MARC::SOURCE_STRING);
			if ($marcRecord = $marc->next()) {
				$descriptiveInfo = Description::loadDescriptionFromMarc($marcRecord);
			}

			$titles[] = array(
          'id' => $record['id'],
          'image' => $configArray['Site']['coverUrl'] . "/bookcover.php?id=" . $record['id'] . "&isn=" . $isbn . "&size=medium&upc=" . (isset($record['upc']) ? $record['upc'][0] : '') . "&category=" . $record['format_category'][0],
          'title' => $record['title'],
          'author' => isset($record['author']) ? $record['author'] : '',
			    'description' => $descriptiveInfo['description'],
          'length' => isset($descriptiveInfo['length']) ? $descriptiveInfo['length'] : null,
          'publisher' => isset($descriptiveInfo['publisher']) ? $descriptiveInfo['publisher'] : null,
			);
		}

		$listCache->jsonData = json_encode($titles);
		$listCache->cacheDate = time();
		$listCache->insert();
		return $titles;
	}

	function getSystemListTitles($listName){
		require_once('sys/Cache/ListCache.php');
		//Check to see if the list has been cached and is recent
		$listCache = new ListCache();
		$listCache->listName = $listName;
		if ($listCache->find(true)){
			return json_decode($listCache->jsonData, true);
		}

		global $configArray;
		require_once('services/Record/Description.php');
		//return a random selection of 30 titles from the list.
		$scrollerName = $_GET['scrollerName'];
		$searchObj = SearchObjectFactory::initSearchObject();
		$searchObj->init();
		$searchObj->setBasicQuery("*:*");
		$searchObj->addFilter("system_list:$listName");
		$searchObj->setLimit(50);
		$searchObj->processSearch(false, false);
		$matchingRecords = $searchObj->getResultRecordSet();

		$titles = array();
		foreach ($matchingRecords as $record){
			$isbn = $record['isbn'][0];
			if (strpos($isbn, ' ') > 0){
				$isbn = substr($isbn, 0, strpos($isbn, ' '));
			}

			// Process MARC Data
			$marc = trim($record['fullrecord']);
			$marc = preg_replace('/#31;/', "\x1F", $marc);
			$marc = preg_replace('/#30;/', "\x1E", $marc);
			$marc = new File_MARC($marc, File_MARC::SOURCE_STRING);
			if ($marcRecord = $marc->next()) {
				$descriptiveInfo = Description::loadDescriptionFromMarc($marcRecord);
			}


			$titles[] = array(
          'id' => $record['id'],
          'image' => $configArray['Site']['coverUrl'] . "/bookcover.php?id=" . $record['id'] . "&isn=" . $isbn . "&size=medium&upc=" . $record['upc'][0] . "&category=" . $record['format_category'][0],
          'title' => $record['title'],
          'author' => $record['author'],
			    'description' => $descriptiveInfo['description'],
			    'length' => $descriptiveInfo['length'],
			    'publisher' => $descriptiveInfo['publisher'],
			);
		}

		$listCache->jsonData = json_encode($titles);
		$listCache->cacheDate = time();
		$listCache->insert();
		return $titles;
	}
}