jQuery(function ($) {
    var $wp_inline_edit = inlineEditPost.edit;

    inlineEditPost.edit = function (id) {
        $wp_inline_edit.apply(this, arguments);

        var postId = 0;
        if (typeof id === 'object') {
            postId = parseInt(this.getId(id));
        } else {
            postId = parseInt(id);
        }

        if (postId <= 0) return;

        var $row = $('#post-' + postId);
        var priceCell = $row.find('td.fcc_price').text().trim();

        // Parse "16oz: $6.25 / 24oz: $8.25" back into fields
        var $editRow = $('#edit-' + postId);
        var parts = priceCell.split('/').map(function (p) { return p.trim(); });

        if (parts[0]) {
            var match0 = parts[0].match(/^(.*?):\s*\$?([\d.]+)$/);
            if (match0) {
                $editRow.find('input[name="fcc_size_option_1"]').val(match0[1].trim());
                $editRow.find('input[name="fcc_price_option_1"]').val(match0[2]);
            } else {
                var priceOnly0 = parts[0].match(/\$?([\d.]+)/);
                if (priceOnly0) {
                    $editRow.find('input[name="fcc_price_option_1"]').val(priceOnly0[1]);
                }
            }
        }

        if (parts[1]) {
            var match1 = parts[1].match(/^(.*?):\s*\$?([\d.]+)$/);
            if (match1) {
                $editRow.find('input[name="fcc_size_option_2"]').val(match1[1].trim());
                $editRow.find('input[name="fcc_price_option_2"]').val(match1[2]);
            } else {
                var priceOnly1 = parts[1].match(/\$?([\d.]+)/);
                if (priceOnly1) {
                    $editRow.find('input[name="fcc_price_option_2"]').val(priceOnly1[1]);
                }
            }
        }
    };
});
