// app.js - Doxera+ jQuery helpers
$(function(){
  // AJAX global setup
  $.ajaxSetup({ headers: { 'X-Requested-With': 'XMLHttpRequest' } });

  // Notifications poll (optionnel)
  function loadNotifications(){
    $.get(BASE_URL + '/api/notifications.php', function(data){
      // inject if container exists
    });
  }

  // Confirm delete
  $(document).on('click', '.js-confirm', function(e){
    if(!confirm($(this).data('confirm')||'Confirmer ?')) e.preventDefault();
  });

  // Search autocomplete
  $('#globalSearch').on('input', function(){
    let q=$(this).val();
    if(q.length<2) return $('#searchResults').hide();
    $.get(BASE_URL+'/api/search.php',{q:q}, function(res){
      let html='';
      (res||[]).forEach(d=>{
        html+=`<a href="${BASE_URL}/dashboard/dossiers/view.php?id=${d.id}" class="block px-3 py-2 hover:bg-zinc-50 text-sm"><b>${d.reference}</b> ${d.title}</a>`;
      });
      $('#searchResults').html(html).show();
    },'json');
  });
});
