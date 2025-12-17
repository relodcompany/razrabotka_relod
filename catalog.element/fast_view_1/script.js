$(document).on('click', '.navigation-wrapper-fast-view .fast-view-nav', function(){
	var _this = $(this);

	if(_this.hasClass('noAjax')) {
		return false;
	}
    
	_this.addClass('noAjax');

	var data = _this.data('ajax');
	data.action = _this.data('fast-nav');
	var container = _this.closest('#popup_iframe_wrapper').find('.fast_view_frame');

    $('#fast_view_item').find('.form').addClass("sending");

	$.ajax({
		url: arAsproOptions['SITE_DIR'] + 'ajax/fastViewNav.php',
		type: "POST",
		data: data,
		success: function(result){
			$('#fast_view_item').append(result);
			_this.removeClass('noAjax');
		}
	});
});

window.addEventListener('keydown', function(e){
    if (e.keyCode == 37) {
        var navPrev = $('#popup_iframe_wrapper .navigation-wrapper-fast-view .fast-view-nav.prev');
        if(navPrev.length) {
        	navPrev.click();
        }
    }
    else if(e.keyCode == 39) {
	 	var navNext = $('#popup_iframe_wrapper .navigation-wrapper-fast-view .fast-view-nav.next');
        if(navNext.length) {
        	navNext.click();
        }
    }
});