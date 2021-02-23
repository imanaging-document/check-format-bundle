$('#btn_controle_fichier').click(function() {
  var div_controle = $('#div_controle');
  div_controle.addClass('d-none');
  var spinner = $('#spinner_integration');
  spinner.removeClass('d-none');
  var div_resultat = $('#div_resultat_import');
  $.ajax({
    url: $('#btn_controle_fichier').data('url'),
    type: 'POST',
    success: function(data){
      div_resultat.html(data);
      spinner.addClass('d-none');
    },
    error: function(){
      spinner.addClass('d-none');
      showSweetAlert('top-end', 'error', "Une erreur est survenue lors du contrôle du fichier. Veuillez réessayer plus tard.", true, 3000);
    }
  });
});