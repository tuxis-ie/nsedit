/*!
 Author: Stephen Korecky
 Website: http://stephenkorecky.com
 Plugin Website: http://github.com/skorecky/Add-Clear
 Version: 2.0.6

 The MIT License (MIT)

 Copyright (c) 2015 Stephen Korecky

 Permission is hereby granted, free of charge, to any person obtaining a copy
 of this software and associated documentation files (the "Software"), to deal
 in the Software without restriction, including without limitation the rights
 to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 copies of the Software, and to permit persons to whom the Software is
 furnished to do so, subject to the following conditions:

 The above copyright notice and this permission notice shall be included in all
 copies or substantial portions of the Software.

 THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 SOFTWARE.
*/

;(function($, window, document, undefined) {

	// Create the defaults once
	var pluginName = "addClear",
		defaults = {
			closeSymbol: "&#10006;",
			color: "#CCC",
			top: 1,
			right: 4,
			returnFocus: true,
			showOnLoad: false,
			onClear: null,
			hideOnBlur: false,
			tabbable: true,
			paddingRight: '20px'
		};

	// The actual plugin constructor
	function Plugin(element, options) {
		this.element = element;

		this.options = $.extend({}, defaults, options);

		this._defaults = defaults;
		this._name = pluginName;

		this.init();
	}

	Plugin.prototype = {

		init: function() {
			var $this = $(this.element),
					$clearButton,
					me = this,
					options = this.options;

			$this.wrap("<span style='position:relative;' class='add-clear-span'></span>");
			var tabIndex = options.tabbable ? "" : " tabindex='-1'";
			$clearButton = $("<a href='#clear' style='display: none;'" + tabIndex + ">" + options.closeSymbol + "</a>");
			$this.after($clearButton);
			$this.next().css({
				color: options.color,
				'text-decoration': 'none',
				display: 'none',
				'line-height': 1,
				overflow: 'hidden',
				position: 'absolute',
				right: options.right,
				top: options.top
			}, this);

			if (options.paddingRight) {
				$this.css({
					'padding-right': options.paddingRight
				});
			}

			if ($this.val().length >= 1 && options.showOnLoad === true) {
				$clearButton.css({display: 'block'});
			}

			$this.focus(function() {
				if ($(this).val().length >= 1) {
					$clearButton.css({display: 'block'});
				}
			});

			$this.blur(function(e) {
				if (options.hideOnBlur) {
					setTimeout(function() {
						var relatedTarget = e.relatedTarget || e.explicitOriginalTarget || document.activeElement;
						if (relatedTarget !== $clearButton[0]) {
							$clearButton.css({display: 'none'});
						}
					}, 0);
				}
			});

			var handleUserInput = function() {
				if ($(this).val().length >= 1) {
					$clearButton.css({display: 'block'});
				} else {
					$clearButton.css({display: 'none'});
				}
			};

			var handleInput = function () {
			    $this.off('keyup', handleUserInput);
				$this.off('cut', handleUserInput);
				handleInput = handleUserInput;
				handleUserInput.call(this);
			};

			$this.on('keyup', handleUserInput);

			$this.on('cut', function () {
				var self = this;
				setTimeout(function () {
					handleUserInput.call(self);
				}, 0);
			});

			$this.on('input', function () {
				handleInput.call(this);
			});

			if (options.hideOnBlur) {
				$clearButton.blur(function () {
					$clearButton.css({display: 'none'});
				});
			}

			$clearButton.click(function(e) {
				var $input = $(me.element);
				$input.val("");
				$(this).css({display: 'none'});
				if (options.returnFocus === true) {
					$input.focus();
				}
				if (options.onClear) {
					options.onClear($input);
				}
				e.preventDefault();
			});
		}

	};

	$.fn[pluginName] = function(options) {
		return this.each(function() {
			if (!$.data(this, "plugin_" + pluginName)) {
				$.data(this, "plugin_" + pluginName,
					new Plugin(this, options));
			}
		});
	};

})(jQuery, window, document);
