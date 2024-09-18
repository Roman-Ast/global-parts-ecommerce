import './bootstrap';
import './jquery.min.js';

$(function (params) {
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });
    
    $('#search-btn').on('click', function(e) {
        e.preventDefault();

        let partNumber = $('#searchBarInput').val();
        
        $.ajax({
            data: { partNumber },
            url: "{{ route('getSparePart') }}",
            type: "POST",
            dataType: 'json',
            success: function (response) {
                console.log(response);
            },
            error: function (error) {
                console.log(error);
            }
        });
    });
});



