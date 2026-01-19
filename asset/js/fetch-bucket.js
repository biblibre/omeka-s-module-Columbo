(($) => {
    document.addEventListener('DOMContentLoaded', () => {
        element = document.getElementById('columbo-bucket-size');
        $.ajax({url: '/admin/columbo/fetchBucketSize',
            success: (result) => {
                element.textContent = result;
            },
            error: (result) => {
                element.setHTMLContent(Omeka.jsTranslate('Could not calculate bucket size.'));
                console.log(result);
            }
        })
    })
})(jQuery);