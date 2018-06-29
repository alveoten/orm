function qv_toggle(elem, selector) {
    $(".qv_bar button").css({'border-bottom': '0px solid black'});
    if ($(selector).is(":visible")) {
        $(selector).hide();
    }
    else {
        $('.qv_list').hide();
        $(selector).show();
        $(elem).css({'border-bottom': '2px solid #fff'});
    }
}

$(document).ready(function () {
    /*dt = $('#qv_query_table').DataTable({
        "bPaginate": false,
        "bLengthChange": true,
        "bFilter": false,
        "bInfo": false,
        "bAutoWidth": true
    });


    // Sort immediately with columns 0 and 1
    dt.fnSort([[4, 'desc']]);*/
});

$('.enlarge_your_query').click(function(){
    $td = $(this).closest('td');
    if(!$td.find('.formatted').is(':visible')) {
        $td.find('.non_formatted').hide();
        $td.find('.formatted').show();
        $(this).removeClass('fa-magic').addClass('fa-times');
    }
    else{
        $td.find('.non_formatted').show();
        $td.find('.formatted').hide();
        $(this).removeClass('fa-times').addClass('fa-magic');
    }
});

function qv_explain(id,elem) {
    elemTo = elem;
    /*$.post('/debuggerBar?action=ajaxExplain',
        {
            sql: $(elem).closest('.sql_container').find('.sql').text()
        },
        function (html) {
            $(elemTo).mouseout();
            $(elemTo).after(html);
            $(elemTo).remove();
        }
    );*/
}