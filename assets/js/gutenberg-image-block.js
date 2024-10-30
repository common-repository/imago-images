if(!Array.isArray(gutenbergImageBlockSettings.creditSettings))
	gutenbergImageBlockSettings.creditSettings = [];

wp.hooks.addFilter(
    'editor.BlockListBlock',
    'imago/image-block-edit',
    function (BlockEdit) {
		'use strict';
		
		return function ( props ) {
			// If we are dealing with an image block, treat it special.
			if(props.name === 'core/image' && props.attributes.id) {
				
				// Check all the setting variables.
				var noImageCredit = gutenbergImageBlockSettings.creditSettings.indexOf('no-imago-credits') > -1,
					requireAuthorCredit = gutenbergImageBlockSettings.creditSettings.indexOf('require-author-crediting') > -1;
				
				// From: https://developer.wordpress.org/block-editor/how-to-guides/metabox/#step-2-add-meta-block
				//const [meta, setMeta] = wp.coreData.useEntityProp('postType', 'attachment', 'meta');
									
				// This waits until we receive the information.
				var img = wp.data.useSelect(function(select) {
					return select('core').getMedia(props.attributes.id);
				});
				
				// These are the elements to be added to the image caption.
				var appendix = [];
				
				// If this is not an Imago image, ignore.
				if(img && "meta" in img && "imago_source_id" in img.meta && img.meta.imago_source_id) {
										
					// If image description is not entered, deny access.
					if('description' in img && 'raw' in img.description && img.description.raw) {
					
						appendix.push(img.description.raw);
						
					} else if(requireAuthorCredit) {
						// Disallow the image from being used.
						props.attributes.url = gutenbergImageBlockSettings.notAllowed;
						props.attributes['imagoCredited'] = props.attributes.url;
					}
					
					// Add the Imago credit.
					if(!noImageCredit) {
						appendix.push(gutenbergImageBlockSettings.imagoCredit.replace('[imago_url]',gutenbergImageBlockSettings.imagoBaseUrl + img.meta.imago_source_id));						
					}
					
				} 
				//else {
					
					// If the image has no Imago metadata record, rescan it to
					// see if it has metadata.
					// wp.ajax.post('scan_attachment_imago_metadata', {
						// 'action': 'scan_attachment_imago_metadata',
						// 'attachmentId': props.attributes && props.attributes.id
					// }).done(function(response) {
						// console.log(response);
					// }).fail(function(error) {
						// // Handle errors
						// console.warn(error.responseText);
					// });
					
				//}
				
				// Add the section if we have content (appendix.length > 0),
				// and if the image hasn't been credited.
				// If we credit the image, we save the image URL that we credit, so if the resource
				// changes, then Gutenberg will know to credit it again.
				if(appendix.length > 0 && (!('imagoCredited' in props.attributes) || props.attributes.url !== props.attributes.imagoCredited)) {					
					// Remove the old credit.
					props.attributes['caption'] = props.attributes['caption'].replace(/<span role="imago-caption">[\s\S]*?<\/span>/i, '');
					
					// Is the caption empty?
					var isCaptionEmpty = props.attributes['caption'].trim() ? false : true;
					
					// Add the new credit and save the current image URL.
					props.attributes['caption'] += '<span role="imago-caption">' + (isCaptionEmpty ? '' : ' | ') + appendix.join(', ') + '</span>';
					props.attributes['imagoCredited'] = props.attributes.url;
				}
			}
			
			return React.createElement(
				BlockEdit, props
			);
		};
	}
);

// Addition of filter for refreshing the Imago image ID.
/*
wp.hooks.addFilter('editor.BlockEdit', 'imago/image-block-settings', wp.compose.createHigherOrderComponent(function (BlockEdit) {
    return function (props) {
        var Fragment = wp.element.Fragment,
			Button = wp.components.Button,
			PanelBody = wp.components.PanelBody,
			InspectorControls = wp.blockEditor.InspectorControls,
			attributes = props.attributes,
            setAttributes = props.setAttributes,
            isSelected = props.isSelected,
			dispatch = wp.data.dispatch,
			__ = wp.i18n.__;
			
        return wp.element.createElement(
            Fragment,
            null,
            wp.element.createElement(BlockEdit, props),
            props.name === 'core/image' && wp.element.createElement(
                InspectorControls,
                null,
				wp.element.createElement(PanelBody, { title: __('Imago Settings', 'imago-images') },
					wp.element.createElement(Button, {
						label: __('Scan for Imago Metadata', 'imago-images'),
						onClick: function() { 
							
							wp.ajax.post('scan_attachment_imago_metadata', {
								'action': 'scan_attachment_imago_metadata',
								'attachmentId': props.attributes && props.attributes.id
							}).done(function(response) {
								
								// Handle the response
								dispatch('core/notices').createNotice(
									'success',
									response,
									{
										'isDismissible': true,
										'speak': true
									}
								);
								
							}).fail(function(error) {
								// Handle errors
								dispatch('core/notices').createNotice(
									'error',
									error.responseText,
									{
										'isDismissible': true,
										'speak': true
									}
								);
							});
						
						},
						isSecondary: true
					}, __('Scan for Imago Metadata', 'imago-images'))
				)
            )
        );
    };
}, 'imageBlockSettings'));
*/