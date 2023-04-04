/* ------------------------------------------------------------------------------
 *
 *  # Custom JS code
 *
 *  Place here all your custom js. Make sure it's loaded after app.js
 *
 * ---------------------------------------------------------------------------- */


/*  This code snippet is needed for including html snippets  */
$(function () {
    var includes = $('[data-include]');
    $.each(includes, function () {
        console.log($(this));
        var file = 'html-blocks/' + $(this).data('include') + '.html'
        $(this).load(file)
    })
});
/*  END This code snippet is needed for including html snippets */