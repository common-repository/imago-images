(function($) {
	'use strict';
	
	if(!wp.media) return;
	
	// Functions we want to override on wp.media.view.MediaFrame.Select.
	// Saving a copy so we can also call these functions in our new overrides.
	var parent = {
		'browseRouter': wp.media.view.MediaFrame.Select.prototype.browseRouter,
		'bindHandlers': wp.media.view.MediaFrame.Select.prototype.bindHandlers,
		'events': wp.media.view.MediaFrame.Select.prototype.events
	};
	
	// Override functions to generate a new tab and view for the Media Library.
	wp.media.view.MediaFrame.Select.prototype.browseRouter = function( routerView ) {
		parent.browseRouter.call(this, routerView);
		routerView.set('imago', {
			'text': "Import from Imago",
			'priority': 60
		});
	};
	
	// Bind an action to the Imago tab.
	wp.media.view.MediaFrame.Select.prototype.bindHandlers = function() {
		parent.bindHandlers.call(this);
		this.on( 'content:render:imago', this.browseImago, this );
	};
	
	// Hook a new function for Imago.
	wp.media.view.MediaFrame.Select.prototype.browseImago = function() {
		this.$el.removeClass( 'hide-toolbar' );
		
		// Unselect the selected image in the Media Library.
		if(this.content.get() && this.content.get().hasOwnProperty('options') && this.content.get().options.hasOwnProperty('selection'))
			this.content.get().options.selection.reset();
		
		// Initialise the Imago view.
		this.content.set(new wp.media.view.ImagoUploaderView({
			controller: this
		}));
	};
	
	// Creating a new view class for Imago.
	wp.media.view.ImagoUploaderView = wp.media.View.extend({
		'tagName': 'div',
		'className': 'imago-uploader-view imago-tab-content attachments-browser',
		
		'template': wp.template('imago-uploader-view'),
		'sidebarTemplate': wp.template('imago-media-sidebar'),
		'errorDetailsTemplate': wp.template('imago-error-details'),
		'errorMessageTemplate': wp.template('imago-error-message'),
		
		'events': _.extend(parent.events, {
			'submit form[role="search"]': 'handleSearch',
			'click a[data-flip]': 'handlePageChange',
			'click .imago-pic': 'handleImageSelected',
			'submit form#imago-download-form': 'handleAddImage'
		}),
		
		'initialize': function(options) {
			wp.media.View.prototype.initialize.call(this, options);
			
			_.bindAll(this,'handleSearch','handlePageChange','handleImageSelected','handleAddImage');
		},
		
		// Returns the data to render the view.
		'prepare': function() {
			if(this._isFirstLoad) {
				this._isFirstLoad = false;
				return {
					'image_data': [],
					'search_value': '',
					'total_pages': 0
				};
			}
			
			return this.options;
		},
		
		'refresh': function() {
			var that = this;
			
			// Debug the sent query.
			if(this.DEBUG) console.log('Sending query: ', this.query);
			
			this.$searchForm.find('input').prop('disabled',true);
			this.$spinner.addClass('is-active');
			this.$resultsCount.hide();
			this.$paginator.find('a').attr('disabled','y');
			
			$.ajax({
				'type': 'post',
				'dataType': 'json',
				'url': imagoTabSettings.ajaxurl,
				'data': this.query,
				'success': function(response){
					if(response['success']) {
						that.options = response['data'];
						that.render();
					} else {
						// If the response failed, we show a message.
						that.$searchForm.find('input').prop('disabled',false);
						that.$spinner.removeClass('is-active');
						that.$resultsCount.show();
						that.$mediaSidebar.html(that.errorDetailsTemplate(response['data']));
						that.$el.find('.attachments').html(that.errorMessageTemplate(response['data']));
					}
					
					// Debug message.
					if(that.DEBUG) console.log('Received: ',response);
				},
				
				'error': function(error) {
					console.log('Error: ',error.responseText);
				}
			});
		},
		
		'render': function() {
			wp.media.View.prototype.render.call(this);
			
			// Re-find the important elements.
			this.$mediaSidebar = this.$el.children('.media-sidebar');
			this.$searchForm = this.$el.find('form[role="search"]');
			this.$spinner = this.$searchForm.children('.spinner');
			this.$resultsCount = this.$spinner.siblings('em');
			this.$paginator = this.$el.find('#paginator');
		},
		
		'handleSearch': function(evt) {
			evt.preventDefault();
			
			// Get the search field and use it.
			var $searchFields = $(evt.currentTarget);
			if($searchFields.length > 0) {
				// Update the options then refresh the page.
				this.query = {
					'action': 'imago_query',
					'search_value': $searchFields.find('input[name="search"]').val(),
					'page': 0
				};
				
				this.refresh();
			}
			
			return false;
		},
		
		// Can receive either an event object or an integer.
		'handlePageChange': function(evt) {
			switch(typeof evt) {
				case 'number':
					var flip = parseInt(evt);
					if(flip !== 0) {
						this.query['page'] = Math.max(0, this.query['page'] + flip);
						this.refresh();
					}
					break;
				case 'object':
					if(evt.currentTarget) {
						if(evt.currentTarget.getAttribute("disabled")) return;
						var flip = parseInt(evt.currentTarget.getAttribute("data-flip"));
						if(flip !== 0) {
							this.query['page'] = Math.max(this.query['page'] + flip);
							this.refresh();
						}
					}
					break;
			}
		},
		
		'handleImageSelected': function(evt) {
			var idx = parseInt(evt.currentTarget.getAttribute("data-id"));
			if(idx > -1) {
				if(this.DEBUG)
					console.log('Selected: ', this.options['image_data'][idx]);
				
				this.$mediaSidebar.html(this.sidebarTemplate( 
					_.extend(this.options['image_data'][idx],{'id': idx})
				));
			}
		},
		
		'handleAddImage': function(evt) {
			evt.preventDefault();
			
			var $form = $(evt.currentTarget),
				submitButton = document.activeElement,
				id = parseInt(evt.currentTarget.getAttribute('data-id')),
				data = {
					'action': 'imago_add_to_media_library',
					'pictureid': evt.currentTarget.getAttribute('data-pictureid'), 
					'db': evt.currentTarget.getAttribute('data-db'),
					'filename': $form.find('input[name="filename"]').val(),	
					'res': $form.find('select[name="res"]').val(),
					'caption': this.options["image_data"][id].caption,
					'source': this.options["image_data"][id].source
				}, that = this;
				
			// If there is a maxres attribute, we also add that.
			var $maxres = $form.find('input[name="maxres"]');
			if($maxres.length > 0) data['maxres'] = $maxres.val();
			
			submitButton.disabled = true;
			
			// Prints the data query sent.
			if(this.DEBUG) console.log('Adding image: ', data);
			var $dlStatus = $form.find('.dl-status').html('Downloading image <span class="spinner is-active"></span></div>');
			
			// The actual AJAX query.
			$.ajax({
				'type' : 'post',
				'dataType' : 'json',
				'url' : imagoTabSettings.ajaxurl,
				'data': data, 
				'success': function(response){
					
					if(that.DEBUG) console.log('Add to library response: ',response);
					
					// Check if AJAX has succeeded.
					if(response.success) {
						$dlStatus.html('Added to Media library.').removeClass('dl-status-error');
						
						// Go back to the Media Library, then refresh it.
						that.controller.content.mode('browse');
						that.controller.content.get().collection.props.set({ignore: (+ new Date())});
						
						// Select the most recently-added image.
						that.controller.content.get().options.selection.set(
							that.controller.content.get().collection.first()
						);
					} else {
						$dlStatus.html('<strong>Download error</strong><br/>' + response.data).addClass('dl-status-error');
					}
					
					submitButton.disabled = false;
				},
				'error': function(error) {
					$dlStatus.html('<strong>AJAX error</strong><br/>' + error.responseText).addClass('dl-status-error');
					console.log(error.responseText);
					submitButton.disabled = false;
				}
			});
			
			return false;
		},
		
		'_isFirstLoad': true,
		
		'DEBUG': false
	});

})(jQuery);