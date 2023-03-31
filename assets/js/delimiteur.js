
jQuery(document).ready(function () {
    $("span.bundleContentPreview").each(function () {
        if ($(this).attr("id").includes("599") == true) {
            $(this).parent().addClass("delimiteur-titre");
        }
    })
    $("span.bundleContentPreview").each(function () {
        if ($(this).attr("id").includes("611") == true) {
            $(this).parent().addClass("delimiteur-soustitre");
        }
    })
});

