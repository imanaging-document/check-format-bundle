$('#btn-upload-file').on('click', function() {
  let btn = $(this);
  let loader = $('#loader-upload');
  btn.addClass('d-none');
  loader.removeClass('d-none');

  let fileData = $('#fichier').prop('files')[0];
  let data = new FormData();
  data.append('file', fileData);
  let formData = $("#custom-form").serializeArray();
  formData.forEach(function(item){
    data.append(item.name, item.value);
  });
  $.ajax({
    url: $(this).data('url'),
    cache: false,
    contentType: false,
    processData: false,
    data: data,
    type: 'POST',
    success: function(){
      location.reload();
    },
    error: function(data) {
      if (data.responseJSON !== undefined){
        $("#error-message").html(data.responseJSON.error_message).removeClass('d-none');
      } else {
        $("#error-message").html('Une erreur inconnue est survenue').removeClass('d-none');
      }
      btn.removeClass('d-none');
      loader.addClass('d-none');
    }
  });
});