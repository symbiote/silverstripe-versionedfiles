<div class="ss-uploadfield-item ss-uploadfield-addfile field">

	<h3>
		<span class="step-label">
			<span class="title"><% _t('VerionedFiles.UPLOADNEWFILE', 'Upload a New File') %></span>
		</span>
	</h3>

	<div class="ss-uploadfield-item-info">
		<label class="ss-uploadfield-fromcomputer ss-ui-button ss-ui-action-constructive" title="<% _t('AssetUploadField.FROMCOMPUTERINFO', 'Upload from your computer') %>" data-icon="drive-upload">
			<% _t('VerionedFiles.TOUPLOAD', 'Choose file to upload...') %>
			<input id="$id" name="$getName" class="$extraClass ss-uploadfield-fromcomputer-fileinput" data-config="$configString" type="file"<% if $multiple %> multiple="multiple"<% end_if %> title="<% _t('VerionedFiles.TOUPLOAD', 'Choose file to upload...') %>" />
		</label>
		
		<div class="clear"><!-- --></div>
	</div>
	<div class="ss-uploadfield-item-uploador">
		<% _t('AssetUploadField.UPLOADOR', 'OR') %>
	</div>
	<div class="ss-uploadfield-item-preview ss-uploadfield-dropzone">
		<div>
			<% _t('AssetUploadField.DROPAREA', 'Drop Area') %>
			<span><% _t('VerionedFiles.DRAGFILEHERE', 'Drag file here') %></span>
		</div>
	</div>
	<div class="clear"><!-- --></div>
</div>

<div class="ss-uploadfield-editandorganize">
	
	<div class="fileOverview">
		<div class="uploadStatus">
			<div class="state"><% _t('AssetUploadField.UPLOADINPROGRESS', 'Please wait… upload in progress') %></div>
			<div class="details"><% _t('AssetUploadField.TOTAL', 'Total') %>: 
				<span class="total"></span> <% _t('AssetUploadField.FILES', 'Files') %> 
				<span class="fileSize"></span> 
			</div>
		</div>		
	</div>
	<ul class="ss-uploadfield-files files"></ul>
</div>