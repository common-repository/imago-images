<?php if (!defined('ABSPATH')) exit; // Exit if accessed directly.
/**
 * The Imago Tab in the Media Library. 
 * @var \Imago\API $api
 * @var array $images The array of Images
 * @var string $search_value
 * @var int $pagination The current page
 * @var int $total_results The total number of pictures there are in this query
 * @var int $total_pages
 */
?><script type="text/html" id="tmpl-imago-uploader-view">
	<div class="media-toolbar">
		<div class="media-toolbar-secondary">
			<form role="search" method="post" action="javascript:" class="imago-search">
				<label for="search"><?php esc_html_e('Image Search:','imago-images'); ?></label>
				<input type="search" name="search" id="search" placeholder="<?php esc_html_e('Search for an image','imago-images'); ?>" value="{{ data.search_value }}">
				<input type="submit" class="button button-primary" value="<?php esc_html_e('Search','imago-images'); ?>"/>
				<span class="spinner"></span>
				<em>
				<# if(data.total_results > 0) { #>
				{{ "Found " + data.total_results + " results." }}
				<# } #>
				</em>
			</form>
		</div>
		<# if(data.total_pages > 0) { #>
		<div class="media-toolbar-primary">
			<div class="tablenav-pages" id="paginator" data-page="{{ data.pagination }}" data-totalresults="{{ data.total_results }}">
				<label><?php esc_html_e('Pages:','imago-images'); ?></label>
				<span class="pagination-links">
					<# data.pagination = parseInt(data.pagination); #>
					<a href="javascript:" data-flip="-1" class="button"<# if(data.pagination <= 0) { #> disabled="y"<# } #>>‹</a>
					<span id="table-paging" class="paging-input">
						<span class="tablenav-paging-text">{{ (data.pagination + 1) + " of " + data.total_pages }}</span>
					</span>
					<a href="javascript:" data-flip="1" class="button"<# if(data.pagination + 1 >= data.total_pages) { #>disabled="y"<# } #>>›</a>
				</span>
			</div>
		</div>
		<# } #>
	</div>
	
	<div class="attachments-wrapper">
		<# if(data.total_results > 0) { #>
		<ul class="attachments">
			<# for(var i = 0; i < data.image_data.length; i++) { #>
				<# if(data.image_data[i].url) {  #>
				<li class="attachment imago-pic" data-picmeta="{{ data.image_data[i].json }}" tabindex="0" role="checkbox" aria-label="image-1" aria-checked="false" data-id="{{ i }}">
					<div class="attachment-preview type-image subtype-jpeg landscape">
						<div class="thumbnail">
							<div class="centered">
								<img src="{{ data.image_data[i].url }}" alt="{{ data.image_data[i].caption }}">
							</div>
						</div>
					</div>
					<button type="button" class="check" tabindex="-1">
						<span class="media-modal-icon"></span>
						<span class="screen-reader-text"><?php esc_html_e('Deselect','imago-images'); ?></span>
					</button>
				</li>
				<# } #>
			<# } #>
		</ul>
		<# } else { #>
		<div class="attachments no-results">
			<label for="search">
				<# if(data.image_data.length > 0) { #>
				<?php esc_html_e('No results found.','imago-images'); ?>
				<# } else { #>
				<?php esc_html_e('Enter a search term to begin.','imago-images'); ?>
				<# } #>
			</label>
		</div>
		<# } #>
	</div>

	<div class="media-sidebar">
	</div>
</script>

<script type="text/template" id="tmpl-imago-media-sidebar">
	<div class="attachment-info">
        <div class="details">
			<h2>Picture ID: {{ data.pictureid }}</h2>
			<form id="imago-download-form" method="post" action="javascript:" data-id="{{data.id}}" data-pictureid="{{ data.pictureid }}" data-db="{{ data.db }}">
				<figure>
					<img src="{{ data.url.replace('smalls','thumbs') }}" alt="{{data.caption}}"/>
				</figure>
				<fieldset>
					<label for="filename">
						<?php esc_html_e('URL Slug:','imago-images'); ?>
					</label><br/>
					<input name="filename" id="filename" value="{{ 
						data.caption.toLowerCase().split(/\s+/g).slice(0,5).join('-').replace(/[^a-z0-9\-]/g,'').replace(/-+/g,'-')
					}}-{{ data.pictureid }}" placeholder="Enter a filename" required="y"/>
					<span>.jpg</span>
				</fieldset>
				<fieldset>
					<label for="res"><?php esc_html_e('Original Resolution:','imago-images'); ?></label>
					<select name="res" id="res">
						<option value="1"><?php esc_html_e('Thumbnail (192px)','imago-images'); ?></option>
						<option value="2"><?php esc_html_e('Small (420px)','imago-images'); ?></option>
						<option value="4"><?php esc_html_e('Medium w/ watermark (1000px)','imago-images'); ?></option>
						<option value="8"><?php esc_html_e('Medium w/o watermark (1000px)','imago-images'); ?></option>
						<option value="9" selected="y"><?php esc_html_e('High (> 1000px)','imago-images'); ?></option>
					</select>
				</fieldset>
				<?php if(get_option(\Terresquall\Imago\Settings::IMAGO_IMAGE_EDITOR,'disabled') !== 'disabled') { ?>
				<fieldset>
					<label for="maxres">
						<?php esc_html_e('Max Resolution:','imago-images'); ?>
						<!--em class="dashicons-before dashicons-editor-help" data-tooltip="<?php esc_html_e('Resolution to downsize the image to.','imago-images'); ?>"></em-->
					</label><br/>
					<input type="number" name="maxres" id="maxres" min="1" max="2560" step="1" placeholder="2560" value="<?php
						echo get_option(\Terresquall\Imago\Settings::IMAGO_IMAGE_MAX_RESOLUTION,'2560')
					?>"/> <?php esc_html_e('pixels','imago-images'); ?>
				</fieldset>
				<?php } ?>
				<fieldset>
					<input type="submit" class="button button-primary" value="<?php
						esc_html_e('Add to Media Library','imago-images');
					?>"/>
				</fieldset>
				<div class="dl-status"></div>
			</form>

			<hr/>
			<p><em>{{ data.caption }}</em></p>
			</hr>
			<h2><?php esc_html_e('Additional Information','imago-images'); ?></h2>
			<table>
				<tr><th><?php esc_html_e('Original:','imago-images'); ?></th><td>{{ data.width }} &times; {{ data.height }} <?php _e('(pixels)','imago-images'); ?></td></tr>
				<tr><th><?php esc_html_e('Created:','imago-images'); ?></th><td>{{ data.datecreated }}</td></tr>
				<tr><th><?php esc_html_e('Archived:','imago-images'); ?></th><td>{{ data.archivaldate }}</td></tr>
				<tr><th><?php esc_html_e('Source:','imago-images'); ?></th><td>{{ data.source }}</td></tr>
				<tr><th><?php esc_html_e('Database:','imago-images'); ?></th><td>{{ data.db }}</td></tr>
				<tr><th><?php esc_html_e('Creative:','imago-images'); ?></th><td>{{ data.editorial }}</td></tr>
				<tr><th><?php esc_html_e('License:','imago-images'); ?></th><td>{{ data.licencegroup }}</td></tr>
			</table>
        </div>
    </div>
</script>

<script type="text/template" id="tmpl-imago-error-details">
	<# if(data.error) { #>
		<h3>{{data.error.type}}</h3>
		<p><strong><?php esc_html_e('Error Code:','imago-images'); ?></strong> {{data.statusCode}}<br/>{{data.error.description}}</p>
	<# } else { #>
		<h3><?php esc_html_e('No error information provided.','imago-images'); ?></h3>
	<# } #>
</script>

<script type="text/template" id="tmpl-imago-error-message">
	<# if(data.statusCode === 401) { #>
		<h1><?php esc_html_e('Error retrieving results from Imago:','imago-images'); ?></h1>
		<p><?php printf(
			esc_html__('Your Imago API credentials are probably wrong. Please double check it in the %s.', 'imago-images'),
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( add_query_arg( 'page', \Terresquall\Imago\Settings::PAGE_SLUG, get_admin_url() . 'options-general.php' ) ),
				esc_html__('Settings page')
			)
		); ?></p>
	<# } else {	#>
		<# if(data.title) { #>
			<h1>{{ data.title }}</h1>
			<p>{{ data.message }}</p>
		<# } else { #>
			<h1><?php esc_html_e('An undocumented error has occurred.','imago-images'); ?></h1>
		<# } #>
	<# } #>
</script>