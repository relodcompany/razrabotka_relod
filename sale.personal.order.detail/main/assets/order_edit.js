;(function($){
  $(document).ready(function(){

    // Открываем модалку и подгружаем форму
    $('#js-edit-order').on('click', function(){
      var orderId = $(this).data('order-id');
      var $modal = $('#modal-edit-order');
      var $body  = $('#js-edit-order-body');

      // Показываем модалку
      $modal.addClass('lc-modal--open');
      // Плейсхолдер «загрузка»
      $body.html('<p>Загрузка формы редактирования…</p>');

      // AJAX-запрос за html-формой
      $.get(
        '/local/ajax/getOrderEditForm.php', // ваш скрипт, который вернёт форму
        { orderId: orderId },
        function(html){
          $body.html(html);
        }
      );
    });

    // Закрываем модалку по крестику или кнопке «Отмена»
    $('#js-edit-order-close, #js-edit-order-cancel').on('click', function(){
      $('#modal-edit-order').removeClass('lc-modal--open');
    });

    // Сохраняем изменения
    $('#js-edit-order-save').on('click', function(){
      var $form = $('#modal-edit-order form');
      if(!$form.length){
        alert('Форма не найдена');
        return;
      }
      var data = $form.serialize();

      $.post(
        '/local/ajax/saveOrderEdit.php', // ваш скрипт, который сохранит изменения
        data,
        function(response){
          if(response.success){
            // либо обновляем страницу, либо закрываем модалку и меняем часть контента
            location.reload();
          } else {
            // показать ошибки
            alert(response.error || 'Ошибка сохранения');
          }
        },
        'json'
      );
    });

  });
})(jQuery);
