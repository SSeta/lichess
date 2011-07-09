$(function() {

    function prepareForm() {
        var $form = $('div.lichess_overboard');
        $form.find('div.buttons').buttonset().disableSelection();
        $form.find('button.submit').button().disableSelection();
        $form.find('.time_choice input, .increment_choice input').each(function() {
            var $input = $(this), $value = $input.parent().find('span');
            $input.hide().after($('<div>').slider({
                value: $input.val(),
                min: $input.data('min'),
                max: $input.data('max'),
                range: 'min',
                step: 1,
                animate: true,
                slide: function( event, ui ) {
                    $value.text(ui.value);
                    $input.attr('value', ui.value);
                    $form.find('.color_submits button').toggle(
                        $form.find('.time_choice input').val() > 0 || $form.find('.increment_choice input').val() > 0
                    );
                }
            }));
        });
        $form.find('.clock_choice input').bind('change', function() {
            $form.find('.time_choice, .increment_choice').toggle($(this).is(':checked'));
            $.centerOverboard();
        }).trigger('change');
        $form.prepend($('<a class="close"></a>').click(function() {
            $form.remove();
            $('#start_buttons a.active').removeClass('active');
        }));
    }

    $('#start_buttons a').click(function() {
        $('#start_buttons a.active').removeClass('active');
        $(this).addClass('active');
        $('div.lichess_overboard').remove();
        $.centerOverboard();
        $.ajax({
            url: $(this).attr('href'), 
            success: function(html) {
                $('div.lichess_board_wrap').prepend(html);
                prepareForm();
                $.centerOverboard();
            }
        });
        return false;
    });
    if ($button = $('#start_buttons a.config_'+window.location.hash.replace(/#/, '')).orNot()) {
        $button.click();
    }
});