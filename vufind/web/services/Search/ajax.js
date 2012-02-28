var GetRatingsList = new Array();
var GetEContentRatingsList = new Array();
var GetSaveStatusList = new Array();
var GetStatusList = new Array();
var GetEContentStatusList = new Array();
var GetOverDriveStatusList = new Array();

function createRequestObject() {  
    // find the correct xmlHTTP, works with IE, FF and Opera
    var xmlhttp;
    try {
        xmlhttp = new ActiveXObject("Msxml2.XMLHTTP");
    } catch(e) {
        try {
            xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
        } catch(e) {
            xmlhttp = null;
        }
    }
    
    if (!xmlhttp && typeof XMLHttpRequest!="undefined") {
        xmlhttp = new XMLHttpRequest();
    }
    
    return xmlhttp;
}

function getElem(id)
{
    if (document.getElementById) {
        return document.getElementById(id);
    } else if (document.all) {
        return document.all[id];
    }
}

function getThumbnail(id, imgname)
{
    var http = createRequestObject();
    http.open("GET", path + "/Search/AJAX?method=GetThumbnail&isn="+id+"&size=small", true);
    http.onreadystatechange = function()
    {
        if ((http.readyState == 4) && (http.status == 200)) {
            var response = http.responseXML.documentElement;
            if (response.getElementsByTagName('image').item(0)) {
                var url = response.getElementsByTagName('image').item(0).firstChild.data;
                alert(url);
                // write out response
                if (url) {
                    document[imgname].src = url;
                } else {
                    document[imgname].src = path + '/images/noCover2.gif';
                }
            } else {
                document[imgname].src = path + '/images/noCover2.gif';
            }
        }
    };
    http.send(null);
}

function addIdToStatusList(id, type) {
	if (type == undefined){
		type = 'VuFind';
	}
	if (type == 'VuFind'){
		GetStatusList[GetStatusList.length] = id;
	}else if (type == 'OverDrive'){
		GetOverDriveStatusList[GetOverDriveStatusList.length] = id;
	}else{
		GetEContentStatusList[GetEContentStatusList.length] = id;
	}
}

function doGetStatusSummaries()
{
	var now = new Date();
	var ts = Date.UTC(now.getFullYear(),now.getMonth(),now.getDay(),now.getHours(),now.getMinutes(),now.getSeconds(),now.getMilliseconds());

	// Modify this to return status summaries one at a time to improve
	// the perceived performance
	var http = createRequestObject();
	var url = path + "/Search/AJAX?method=GetStatusSummaries";
	for (var j=0; j<GetStatusList.length; j++) {
		url += "&id[]=" + encodeURIComponent(GetStatusList[j]);
	}
	var eContentUrl = path + "/Search/AJAX?method=GetEContentStatusSummaries";
	for (var j=0; j<GetEContentStatusList.length; j++) {
		eContentUrl += "&id[]=" + encodeURIComponent(GetEContentStatusList[j]);
	}
	// url += "&id[]=" + encodeURIComponent($id);
	url += "&time="+ts;
	eContentUrl += "&time=" +ts;

	http.open("GET", url, true);
	http.onreadystatechange = function(){
		if ((http.readyState == 4) && (http.status == 200)) {
        	if (http.responseXML == null){
        		return;
        	}
            var response = http.responseXML.documentElement;
            var items = response.getElementsByTagName('item');
            var elemId;
            var statusDiv;
            var status;
            var reserves;
            var showPlaceHold;
            var placeHoldLink;
            var numHoldable = 0;

            for (var i=0; i<items.length; i++) {
            	try{
	                elemId = items[i].getAttribute('id');
	                
	                // Place hold link
	                if (items[i].getElementsByTagName('showplacehold').item(0) == null || items[i].getElementsByTagName('showplacehold').item(0).firstChild == null){
	                	showPlaceHold = 0;
	                }else{	
	                	showPlaceHold = items[i].getElementsByTagName('showplacehold').item(0).firstChild.data;
	                }
	                
	                // Multi select place hold options
	                if (showPlaceHold == '1'){
	                	numHoldable++;
	                	// show the place hold button
		                var placeHoldButton = $('#placeHold' + elemId );
		                if (placeHoldButton.length > 0){
		                	placeHoldButton.show();
		                }
	                }
	                
	                // Change outside border class.
	                var holdingSum= $('#holdingsSummary' + elemId);
	                if (holdingSum.length > 0){
	                	divClass= items[i].getElementsByTagName('class').item(0).firstChild.data;
	                	holdingSum.addClass(divClass);
	                	
	                	var formattedHoldingsSummary = items[i].getElementsByTagName('formattedHoldingsSummary').item(0).firstChild.data;
		                holdingSum.replaceWith(formattedHoldingsSummary);
	                }
	                
	                
            	}catch (err){
            		alert("Unexpected error " + err);
            	}
            }
            // Check to see if the Request selected button should show
            if (numHoldable > 0){
            	$('.requestSelectedItems').show();
            }	
        }
	};
	http.send(null);
    
	$.ajax({
		url: eContentUrl, 
		success: function(data){
			var items = $(data).find('item');
			$(items).each(function(index, item){
				var elemId = $(item).attr("id") ;
				$('#holdingsEContentSummary' + elemId).replaceWith($(item).find('formattedHoldingsSummary').text());
				if ($(item).find('showplacehold').text() == 1){
					$("#placeEcontentHold" + elemId).show();
				}else if ($(item).find('showcheckout').text() == 1){
					$("#checkout" + elemId).show();
				}else if ($(item).find('showaccessonline').text() == 1){
					$("#accessOnline" + elemId).show();
				}else if ($(item).find('showaddtowishlist').text() == 1){
					$("#addToWishList" + elemId).show();
				}
			});
		}
	});
	
	//Get OverDrive status summaries one at a time since they take several seconds to load
	for (var j=0; j<GetOverDriveStatusList.length; j++) {
		var overDriveUrl = path + "/Search/AJAX?method=GetEContentStatusSummaries";
		overDriveUrl += "&id[]=" + encodeURIComponent(GetOverDriveStatusList[j]);
		$.ajax({
			url: overDriveUrl, 
			success: function(data){
				var items = $(data).find('item');
				$(items).each(function(index, item){
					var elemId = $(item).attr("id") ;
					$('#holdingsEContentSummary' + elemId).replaceWith($(item).find('formattedHoldingsSummary').text());
					if ($(item).find('showplacehold').text() == 1){
						$("#placeEcontentHold" + elemId).show();
					}else if ($(item).find('showcheckout').text() == 1){
						$("#checkout" + elemId).show();
					}else if ($(item).find('showaccessonline').text() == 1){
						$("#accessOnline" + elemId).show();
					}else if ($(item).find('showaddtowishlist').text() == 1){
						$("#addToWishList" + elemId).show();
					}
				});
			}
		});
	}
}

function addRatingId(id, type){
	if (type == undefined){
		type = 'VuFind';
	}
	if (type == 'VuFind'){
		GetRatingsList[GetRatingsList.length] = id;
	}else{
		GetEContentRatingsList[GetEContentRatingsList.length] = id;
	}
}

function doGetRatings(){
	var now = new Date();
	var ts = Date.UTC(now.getFullYear(),now.getMonth(),now.getDay(),now.getHours(),now.getMinutes(),now.getSeconds(),now.getMilliseconds());
	var http = createRequestObject();

	var url = path + "/Search/AJAX";
	var data = "method=GetRatings";
	for (var j=0; j<GetRatingsList.length; j++) {
		data += "&id[]=" + encodeURIComponent(GetRatingsList[j]);
	}
	for (var j=0; j<GetEContentRatingsList.length; j++) {
		data += "&econtentId[]=" + encodeURIComponent(GetEContentRatingsList[j]);
	}
	data += "&time="+ts;

	$.getJSON(url, data,
		function(data, textStatus) {
			var recordRatings = data['standard'];
			for (var id in recordRatings){
				// Load the rating for the title
				if (recordRatings[id].user != null && recordRatings[id].user > 0){
					$('.rate' + id).each(function(index){$(this).rater({'rating':data['standard'][id].user, 'doBindings':false, module:'Record', recordId: id});});
				}else{
					$('.rate' + id).each(function(index){$(this).rater({'rating':data['standard'][id].average, 'doBindings':false, module:'Record', recordId: id});});
				}
				$('.ui-rater-rating-' + id).each(function(index){$(this).text( data['standard'][id].average );});
				$('.ui-rater-rateCount-' + id).each(function(index){$(this).text( data['standard'][id].count );});
			}
			var eContentRatings = data['eContent'];
			for (var id in eContentRatings){
				// Load the rating for the title
				if (eContentRatings[id].user != null && eContentRatings[id].user > 0){
					$('.rateEContent' + id).each(function(index){
						$(this).rater({'rating':eContentRatings[id].user, 'doBindings':false, module:'EcontentRecord', recordId: id });
					});
				}else{
					$('.rateEContent' + id).each(function(index){$(this).rater({'rating':eContentRatings[id].average, 'doBindings':false, module:'EcontentRecord', recordId: id});});
				}
				$('.rateEContent' + id + ' .ui-rater-rating-' + id).each(function(index){$(this).text( eContentRatings[id].average );});
				$('.rateEContent' + id + ' .ui-rater-rateCount-' + id).each(function(index){$(this).text( eContentRatings[id].count );});
			}
		}
	);
}


function saveRecord(id, formElem)
{
    var tags = formElem.elements['mytags'].value;
    var notes = formElem.elements['notes'].value;
    var list = formElem.elements['list'].options[formElem.elements['list'].selectedIndex].value;

    var url = path + "/Record/" + id + "/AJAX";
    var params = "method=SaveRecord&" +
                 "service=VuFind&" +
                 "mytags=" + encodeURIComponent(tags) + "&" +
                 "list=" + list + "&" +
                 "notes=" + encodeURIComponent(notes);
    var callback =
    {
        success: function(transaction) {
            var response = transaction.responseXML.documentElement;
            if (response.getElementsByTagName('result')) {
                var value = response.getElementsByTagName('result').item(0).firstChild.nodeValue;
                if (value == "Done") {
                    // Redraw the statuses to reflect the change:
                    doGetSaveStatuses();
                    hideLightbox();
                } else {
                    getLightbox('Record', 'Save', id, null, 'Add to Favorites');
                }
            } else {
                document.getElementById('popupbox').innerHTML = 'Error: Record not saved';
                setTimeout("hideLightbox();", 3000);
            }
        },
        failure: function(transaction) {
            document.getElementById('popupbox').innerHTML = 'Error: Record not saved';
            setTimeout("hideLightbox();", 3000);
        }
    };
    var transaction = YAHOO.util.Connect.asyncRequest('GET', url+'?'+params, callback, null);
}

function getSaveStatuses(id)
{
    GetSaveStatusList[GetSaveStatusList.length] = id;
}

function doGetSaveStatuses()
{
    if (GetSaveStatusList.length < 1) return;

    var http = createRequestObject();
    var now = new Date();
    var ts = Date.UTC(now.getFullYear(),now.getMonth(),now.getDay(),now.getHours(),now.getMinutes(),now.getSeconds(),now.getMilliseconds());

    var url = path + "/Search/AJAX?method=GetSaveStatuses";
    for (var i=0; i<GetSaveStatusList.length; i++) {
        url += "&id" + i + "=" + encodeURIComponent(GetSaveStatusList[i]);
    }
    url += "&time="+ts;

    http.open("GET", url, true);
    http.onreadystatechange = function()
    {
        if ((http.readyState == 4) && (http.status == 200)) {

            var response = http.responseXML.documentElement;
            var items = response.getElementsByTagName('item');

            for (var i=0; i<items.length; i++) {
                var elemId = items[i].getAttribute('id');

                var result = items[i].getElementsByTagName('result').item(0).firstChild.data;
                if (result != 'False') {
                    var lists = eval('(' + result + ')');
                    var listNames = 'Part of these lists:';
                    for (var j=0; j<lists.length;j++) {
                        listNames += '<br/>';
                        if (lists[j].link.length > 0){
                        	listNames += "<a href='" + lists[j].link + "'>" + jsEntityEncode(lists[j].title) + "</a>";
                        }else{
                        	listNames += jsEntityEncode(lists[j].title);
                        }
                    }
                    getElem('lists' + elemId).innerHTML = '<li>' + listNames + '</li>';
                }
            }
        }
    };
    http.send(null);
}

function showSuggestions(elem)
{
    if ((elem.value != '') && (document.searchForm.suggest.checked)) {
        var http = createRequestObject();
        http.open("GET", path + "/Search/AJAX?method=GetSuggestion&phrase=" + elem.value, true);
        http.onreadystatechange = function()
        {
            if ((http.readyState == 4) && (http.status == 200)) {
                document.getElementById('SuggestionList').style.visibility = 'visible';
                document.getElementById('SuggestionList').innerHTML = '';

                var result = http.responseXML.documentElement.getElementsByTagName('result').item(0).firstChild.data;
                var resultList = result.split("|");

                for (i=0; i<10; i++) {
                    if (i==0) {
                        document.getElementById('SuggestionList').innerHTML = document.getElementById('SuggestionList').innerHTML + '<li class="top"><a href="">' + resultList[i] + '</a></li>';
                    } else {
                        document.getElementById('SuggestionList').innerHTML = document.getElementById('SuggestionList').innerHTML + '<li><a href="">' + resultList[i] + '</a></li>';
                    }
                }
            }
        };
        http.send(null);
    } else {
        document.getElementById('SuggestionList').style.visibility = 'hidden';
        document.getElementById('SuggestionList').innerHTML = '';
    }
}

function getSubjects(phrase)
{
    var liList = '';
    var http = createRequestObject();
    http.open("GET", path + "/Search/AJAX?method=GetSubjects&lookfor=" + phrase, true);
    http.onreadystatechange = function(){
        if ((http.readyState == 4) && (http.status == 200)) {
            var response = http.responseXML.documentElement;
            if (subjects = response.getElementsByTagName('Subject')) {
                for (i = 0; i < subjects.length; i++) {
                    if (subjects.item(i).firstChild) {
                        liList = liList + '<li><a href="">' + subjects.item(i).firstChild.data + '</a></li>';
                    }
                }
                document.getElementById('subjectList').innerHTML = liList;
            }
        }
    };
    http.send(null);
}

function setCookie(c_name,value,expiredays)
{
    var exdate = new Date();
    exdate.setDate(exdate.getDate()+ expiredays);
    document.cookie = c_name + "=" + escape(value) +
        ((expiredays==null) ? "" : ";expires=" + exdate.toGMTString());
}

function getCookie(c_name)
{
    if (document.cookie.length > 0)
    {
        c_start = document.cookie.indexOf(c_name + "=");
        if (c_start != -1){ 
            c_start = c_start + c_name.length + 1;
            c_end = document.cookie.indexOf(";",c_start);
            if (c_end == -1) 
                c_end = document.cookie.length;
            return unescape(document.cookie.substring(c_start,c_end));
        } 
    }
    return "";
}

function parseQueryString(qs, term)
{
    qs = qs + "";
    var list = new Array();
    var elems = qs.split("&");
    for (var i=0; i<elems.length; i++) {
        var pair = elems[i].split("=");
        if (pair[0].substring(0, term.length) != term) {
            list.push(elems[i]);
        }
    }
    return list.join('&');
}

function moreFacets(name)
{
    document.getElementById("more" + name).style.display="none";
    document.getElementById("narrowGroupHidden_" + name).style.display="block";
}
                
function lessFacets(name)
{
    document.getElementById("more" + name).style.display="block";
    document.getElementById("narrowGroupHidden_" + name).style.display="none";
}

function getProspectorResults(prospectorNumTitlesToLoad, prospectorSavedSearchId){
	var url = path + "/Search/AJAX";
	var params = "method=getProspectorResults&prospectorNumTitlesToLoad=" + encodeURIComponent(prospectorNumTitlesToLoad) + "&prospectorSavedSearchId=" + encodeURIComponent(prospectorSavedSearchId);
	var fullUrl = url + "?" + params;
    $.ajax({
	  url: fullUrl,
	  success: function(data) {
    	var prospectorSearchResults = $(data).find("ProspectorSearchResults").text();
    	if (prospectorSearchResults) {
        	if (prospectorSearchResults.length > 0){
        		$("#prospectorSearchResultsPlaceholder").html(prospectorSearchResults);
        	}
        }
  }
	});
}

function getStatuses(id)
{
    GetStatusList[GetStatusList.length] = id;
}

function getStatusSummary($id)
{
    var now = new Date();
    var ts = Date.UTC(now.getFullYear(),now.getMonth(),now.getDay(),now.getHours(),now.getMinutes(),now.getSeconds(),now.getMilliseconds());

    // Modify this to return status summaries one at a time to improve
    // the perceived performance
    var http = createRequestObject();
	var url = path + "/Search/AJAX?method=GetStatusSummaries";
	url += "&id[]=" + encodeURIComponent($id);
    url += "&time="+ts;

    http.open("GET", url, true); 
    http.onreadystatechange = function()
    {
        if ((http.readyState == 4) && (http.status == 200)) {
        	if (http.responseXML == null){
        		return;
        	}
            var response = http.responseXML.documentElement;
            var items = response.getElementsByTagName('item');
            var elemId;
            var statusDiv;
            var status;
            var reserves;
            var showPlaceHold;
            var placeHoldLink;
            var numHoldable = 0;

            for (i=0; i<items.length; i++) {
            	try{
	                elemId = items[i].getAttribute('id');
	                
	                // Change outside border class.
	                holdingSum= $('#holdingsSummary' + elemId);
	                if (holdingSum){
	                	divClass= items[i].getElementsByTagName('class').item(0).firstChild.data;
	                	holdingSum.addClass(divClass);
	                }
	                var formattedHoldingsSummary = items[i].getElementsByTagName('formattedHoldingsSummary').item(0).firstChild.data;
	                holdingSum.replaceWith(formattedHoldingsSummary);
	                
	                // Place hold link
	                if (items[i].getElementsByTagName('showplacehold').item(0) == null || items[i].getElementsByTagName('showplacehold').item(0).firstChild == null){
	                	showPlaceHold = 0;
	                }else{	
	                	showPlaceHold = items[i].getElementsByTagName('showplacehold').item(0).firstChild.data;
	                }
	                
	                // Multi select place hold options
	                if (showPlaceHold == '1'){
	                	numHoldable++;
	                	// Also show the checkbox to select multiple titles at once.
		                selectTitleBox = $("#selected" + elemId );
		                if (selectTitleBox){
		                	selectTitleBox.show();
		                }
	                }
            	}catch (err){
            		alert("Unexpected error " + err);
            	}
            }
            // Check to see if the Request selected button should show
            if (numHoldable > 0){
            	$('.requestSelectedItems').show();
            }	
        }
    };
    http.send(null);
}

function getStatusSummaryMSC(id){
	var now = new Date();
	var ts = Date.UTC(now.getFullYear(),now.getMonth(),now.getDay(),now.getHours(),now.getMinutes(),now.getSeconds(),now.getMilliseconds());

	//Modify this to return status summaries one at a time to improve 
	//the perceived performance
  var http = createRequestObject();
	var url = path + "/Search/AJAX?method=GetStatusSummaries";
	url += "&id[]=" + encodeURIComponent(id);
  url += "&time="+ts;

  http.open("GET", url, true); 
  http.onreadystatechange = function()
  {
      if ((http.readyState == 4) && (http.status == 200)) {
      	if (http.responseXML == null){
      		return;
      	}
          var response = http.responseXML.documentElement;
          var items = response.getElementsByTagName('item');
          var elemId;
          var statusDiv;
          var status;
          var reserves;
          var showPlaceHold;
          var placeHoldLink;
          var numHoldable = 0;

          for (i=0; i<items.length; i++) {
          	var curItem = items[i];
          	try{
                elemId = curItem.getAttribute('id');
                
                //Load call number
                var callNumberSpan= $('#callNumberValue' + elemId);
                var callNumberElement = curItem.getElementsByTagName('callnumber');
                if (callNumberElement == null || callNumberElement.item(0).firstChild == null){
                	callNumberSpan.html("N/A");
                }else{
	                var callNumber = callNumberElement.item(0).firstChild.data;
	                callNumberSpan.html(callNumber);
                }
                
              	//Load location
                var locationSpan= $('#locationValue' + elemId);
                var locationElement = curItem.getElementsByTagName('availableAt');
                if (locationElement == null || locationElement.item(0).firstChild == null){
                	locationSpan.html("N/A");
                }else{
	                var availableAt = locationElement.item(0).firstChild.data;
	                locationSpan.html(availableAt);
                }
                
                //Load status
                var statusSpan= $('#statusValue' + elemId);
                var statusElement = curItem.getElementsByTagName('status');
                if (statusElement == null || statusElement.item(0).firstChild == null){
                	statusSpan.html("Unknown");
                }else{
	                var status = statusElement.item(0).firstChild.data;
	                if (status == "Available At"){
	                	status = "Available";
	                }
	                statusSpan.html(status);
                }
                
                //Load Download Link
                var downloadLinkSpan= $('#downloadLinkValue' + elemId);
                var isDownloadableElement = curItem.getElementsByTagName('isDownloadable');
                if (isDownloadableElement == null || isDownloadableElement.item(0).firstChild == null){
                	//Do nothing
                }else{
                	var isDownloadable = isDownloadableElement.item(0).firstChild.data;
                	if (isDownloadable == 1){
	                	var downloadLinkElement = curItem.getElementsByTagName('downloadLink');
		                var downloadLink = downloadLinkElement.item(0).firstChild.data;
		                var downloadTextElement = curItem.getElementsByTagName('downloadText');
		                var downloadText = downloadTextElement.item(0).firstChild.data;
		                $("#downloadLinkValue" + elemId).html("<a href='" + decodeURIComponent(downloadLink) + "'>" + downloadText + "</a>");
		                $("#downloadLink" + elemId).show();
                	}
                }
          	}catch (err){
          		alert("Unexpected error " + err);
          	}
          }
          //Check to see if the Request selected button should show
          if (numHoldable > 0){
          	$('.requestSelectedItems').show();
          }	
      }
  };
  http.send(null);
}
