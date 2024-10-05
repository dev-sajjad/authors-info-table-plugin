jQuery(document).ready(function($) {
    $(document).on('click', '.ait-pagination__btn:not(.ait-pagination__btn--disabled), .ait-pagination__number', function(e) {
        e.preventDefault();
        var page = $(this).data('page');
        loadAuthorsPage(page);
    });

    $(document).on('change', '#authors-per-page', function() {
        var perPage = $(this).val();
        loadAuthorsPage(1, perPage);
    });

    function loadAuthorsPage(page, perPage) {
        $.ajax({
            url: authorTableAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'load_authors_page',
                page: page,
                per_page: perPage
            },
            success: function(response) {
                if (response.success) {
                    $('#author-info-table-container').html(response.data.html);
                    $('.ait-pagination').replaceWith(response.data.pagination);
                }
            }
        });
    }
});