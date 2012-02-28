<script type="text/javascript" src="{$path}/services/MaterialsRequest/ajax.js"></script>
<div id="page-content" class="content">
	<div id="sidebar-wrapper"><div id="sidebar">
		{include file="MyResearch/menu.tpl"}
		{include file="Admin/menu.tpl"}
	</div></div>

	<div id="main-content">
		<h2>Materials Request Summary Report</h2>
		{if $error}
			<div class="error">{$error}</div>
		{else}
			<div id="materialsRequestFilters">
				Filters:
				<form action="{$path}/MaterialsRequest/SummaryReport" method="get">
					<div>
					<div>
						<label for="period">Period</label> 
						<select name="period" id="period">
							<option value="day" {if $period == 'day'}selected="selected"{/if}>Day</option>
							<option value="week" {if $period == 'week'}selected="selected"{/if}>Week</option>
							<option value="month" {if $period == 'month'}selected="selected"{/if}>Month</option>
							<option value="year" {if $period == 'year'}selected="selected"{/if}>Year</option>
						</select>
					</div>
					<div>
						Date: 
						<label for="startDate">From</label> <input type="text" id="startDate" name="startDate" value="{$startDate}" size="8"/>
						<label for="endDate">To</label> <input type="text" id="endDate" name="endDate" value="{$endDate}" size="8"/>
					</div>
					<div><input type="submit" name="submit" value="Update Filters"/></div>
					</div>
				</form>
			</div>
			
			{* Display results as graph *}
			{if $chartPath}
			<div id="chart">
				<img src="{$chartPath}" />
				</div>
			{/if}

			{* Display results in table*}
			<table id="summaryTable" class="tablesorter">
				<thead>
					<tr>
						<th>Date</th>
						{foreach from=$statuses item=status}
							<th>{$status|translate}</th>
						{/foreach}
					</tr>
				</thead>
				<tbody>
					{foreach from=$periodData item=periodInfo key=period}
						<tr>
							<td>{$period|date_format}</td>
							{foreach from=$statuses key=status item=statusLabel}
								<th>{if $periodInfo.$status}{$periodInfo.$status}{else}0{/if}</th>
							{/foreach}
						</tr>
					{/foreach}
				</tbody>
			</table>
		{/if}
		
		<form action="{$fullPath}" method="get">
			<input type="submit" id="exportToExcel" name="exportToExcel" value="Export to Excel">
		</form>
		
		{* Export to Excel option *}
	</div>
</div>
<script type="text/javascript">
{literal}
	$("#startDate").datepicker();
	$("#endDate").datepicker();
	$("#summaryTable").tablesorter({cssAsc: 'sortAscHeader', cssDesc: 'sortDescHeader', cssHeader: 'unsortedHeader', headers: { 0: { sorter: 'date'} } });
{/literal}
</script>