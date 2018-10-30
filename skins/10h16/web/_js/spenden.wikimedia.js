var formInitialized = false;

$(function() {

    if (($('#membership-type-2').length > 0) && $("#membership-type-2").is(':checked')) {
      $("#address-type-2").parent().hide();
      $("#address-type-1").trigger('click');
    }

    /* slide toggle */
    function initSlideToggle() {
      $('a.slide-toggle').click(function (e) {
        var $toggle = $(this);

        if ($toggle.hasClass('active')) {
          $($toggle.attr('data-slide-rel'))
              .removeClass('opened')
              .slideUp( 600 )
              .animate(
                  {opacity: 0},
                  {queue: false, duration: 600}
              );

          $toggle.removeClass('active');
        } else {
          $($toggle.attr('data-slide-rel'))
              .addClass('opened')
              .slideDown( 600 )
              .animate(
                  {opacity: 1},
                  {queue: false, duration: 600}
              );

          $toggle.addClass('active');
        }

        e.preventDefault();
      });
    }

    /* tab toggle */
    /* Was only used in some old forms, may be deleted if not used by March 2016 */
    function initTabToggle() {
      $('a.tab-toggle').click(function (e) {

        $($(this).attr('data-tab-group-rel')).find('.tab').addClass('no-display');
        $($(this).attr('data-tab-rel')).removeClass('no-display');

        e.preventDefault();
      });
    }


	/* tooltip */
	function initToolTip() {
		var tooltipElements = $( '.tooltip' ),
			tooltipTrackTimeout,

			trackHoverAfterDelay = function ( e ) {
				var tooltipTrackDelaySeconds = 1,
					toolTipEventTarget = $( e.target ).closest( '.tooltip' );
				if ( toolTipEventTarget.length < 1 ) {
					return;
				}
				tooltipTrackTimeout = window.setTimeout(
					function () {
						_paq.push( [ 'trackContentInteractionNode', toolTipEventTarget.get(0), 'hover' ] );
					},
					tooltipTrackDelaySeconds * 1000
				);
			},

			cancelHoverTracking = function() {
				window.clearTimeout( tooltipTrackTimeout );
			};

		tooltipElements.tooltip( {
			position: {
				my: "right-15 center",
				at: "left center"
			}
		} );

		tooltipElements.click( function ( e ) {
			e.preventDefault();
		} );

		tooltipElements.hover( trackHoverAfterDelay, cancelHoverTracking );
    }

    /* styled select boxes */
    function initStyledSelect() {

		function getStyledSelectElement( selectElement ) {
			var styledElementName = '#' + $( selectElement ).attr( 'id' ) + '-button';
			return $( styledElementName );
		}

		function setPlaceholderClass( selectElement, optionElement ) {
			if ( optionElement.data( 'behavior' ) === 'placeholder' ) {
				getStyledSelectElement( selectElement ).addClass( 'placeholder' );
			} else {
				getStyledSelectElement( selectElement ).removeClass( 'placeholder' );
			}

		}

      $( '.ctcol select' ).selectmenu( {
            positionOptions: {
              collision: 'none'
            },
		    change: function ( evt, ui ) {
				setPlaceholderClass( this, ui.item.element );
			},
		    create: function ( evt ) {
				setPlaceholderClass( this, $( 'option:selected', this ) );
			}
          }
	  );
    }

    /* iOS fix - label onclick, see http://stackoverflow.com/questions/7358781/tapping-on-label-in-mobile-safari */
    if (navigator.userAgent.match(/Safari/)) {
      $('label').click(function (evt) {
        evt.stopPropagation();
      });
    }


    initSlideToggle();
    initTabToggle();
    initToolTip();
    initStyledSelect();

    formInitialized = true;

  // Update display of selected period, amount and payment type
  // TODO Move this to a view handler

  $(".interval-radio").click(function () {
    $("#interval-hidden").val($("input[name='recurring']:checked").val());
  });

    /* periode-1 */
    $('.interval-type-select.one-off').change(function(e){
      if ( e.target.checked ) {
        $('.interval-radio').prop('checked', false);
        $('#interval-display').text($( "label[for$='periode-1']" ).first().text());
      }
    });

	$( document.commentForm ).bind( 'submit', function ( event ) {
		event.preventDefault();
		$.ajax( '../add-comment', {
			data: $( this ).serialize(),
			dataType: 'json',
			type: 'POST',
			error: function ( e ){
				var $feedback = $( '#feedback' );
				$feedback.find( '.message' ).remove();
				$feedback.append(
					$( '<div />' )
						.addClass( 'message' )
						.addClass( 'error' )
						.text( 'Die Nachricht konnte auf Grund eines Fehlers nicht verschickt werden.' )
				);
			},
			success: function( response ) {
				var $feedback = $( '#feedback' );
				$feedback.find( '.message' ).remove();
				$feedback.append(
					$( '<div />' )
						.addClass( 'message' )
						.addClass( response.status === 'ERR' ? 'error' : 'success' )
						.text( response.message || 'Vielen Dank! Die Nachricht wurde verschickt!' )
				);
			}
		});
	});

    $( 'table.donors' ).each(function () {
        $( 'p', this ).hide();

        var commentLink = $( '<a href="#">' + $( 'th:last-child', this ).html() + '</a>' );

        $( 'tr td:first-child' ).each( function () {
            if ( !$( this ).siblings().last().html() ) {
                return;
            }

            $( this ).append( commentLink.clone().click( function ( event ) {
                event.preventDefault();
                $( this ).parent( 'td' ).siblings().last().children( 'p' ).toggle();
            }) );
        } );
    });
});
