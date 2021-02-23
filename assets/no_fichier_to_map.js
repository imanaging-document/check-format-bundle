$('#btn-upload-file').on('click', function() {
  let btn = $(this);
  let loader = $('#loader-upload');
  btn.addClass('d-none');
  loader.removeClass('d-none');

  let fileData = $('#fichier').prop('files')[0];
  let data = new FormData();
  data.append('file', fileData);
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
    error: function() {
      btn.removeClass('d-none');
      loader.addClass('d-none');
    }
  });
});