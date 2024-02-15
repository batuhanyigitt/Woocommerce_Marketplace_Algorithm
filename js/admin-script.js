jQuery(document).ready(function($) {

  // Global değişkenler
  var totalPosts = 0;
  var postsProcessed = 0;

  function scorePosts(offset, number) {
      $.post(tasv_ajax.ajax_url, {
          action: 'score_posts',
          offset: offset,
          number: number
      }).done(function(data) {
          postsProcessed += number;
          $('#score-result').html(postsProcessed + ' ürün işlendi. Toplam ürün sayısı: ' + totalPosts + '. Lütfen Bekleyin...');
          if(postsProcessed < totalPosts) {
              scorePosts(postsProcessed, number);
          } else {
              $('#score-result').html('All posts scored successfully.');
          }
      });
  }

  $('#score-all-posts').click(function() {
      totalPosts = parseInt($('#total-posts').val()); // Total posts to be processed
      postsProcessed = 0;
      scorePosts(0, 100);
  });


  function updateUserType(user_id, user_type) {

      $.post(tasv_ajax.ajax_url, {

          action: 'tuvval_update_user_type',

          user_id: user_id,

          user_type: user_type,

          nonce: $('#tuvval_user_type_nonce').val()

      });

  }



  $('body').on('change', '.tuvval-user-type', function () {

      var user_id = $(this).data('user_id');

      var user_type = $(this).data('user_type');

      updateUserType(user_id, user_type);

  });


});


// function scorePosts( offset ) {
//     jQuery.ajax({
//         url: ajaxurl,
//         method: 'POST',
//         data: {
//             action: 'tasv_score_posts',
//             // security: tasv_ajax.ajax_nonce,
//             offset: offset
//         },
//         success: function( response ) {
//             if ( response.data !== 'done' ) {
//                 scorePosts( response.data );
//                 let percentage = ( response.data * 100 ) / total_posts;
//                 jQuery("#progressbar").progressbar({ value: percentage });
//             } else {
//                 jQuery("#loading").hide();
//                 jQuery('#score-result').html('<p>Scoring completed!</p>');
//             }
//         },
//         error: function() {
//             jQuery('#score-result').html('<p>An error occurred.</p>');
//         }
//     });
// }
//
// jQuery("#score-all-posts").click(function() {
//     jQuery("#loading").show();
//     scorePosts( 0 );
// });
