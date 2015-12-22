define(function(require, exports, module) {

    var Validator = require('bootstrap.validator');
    require('common/validator-rules').inject(Validator);
    var DraggableWidget = require('../marker/mange');

    var videoHtml = $('#lesson-dashboard');
    var courseId = videoHtml.data("course-id");
    var lessonId = videoHtml.data("lesson-id");

    var myDraggableWidget = new DraggableWidget({
        element: "#lesson-dashboard",
        addScale: function(scalejson) {
            var url = $('.toolbar-question-marker').data('queston-marker-add-url');
            $.post(url,{questionId:scalejson.subject[0].id,second:10},function(data){
                
            });
            console.log(scalejson);
            return scalejson;
        },
        mergeScale: function(scalejson) {
            console.log(scalejson);
            return scalejson;
        },
        updateScale: function(scalejson) {
            console.log(scalejson);
            return scalejson;
        },
        deleteScale: function(scalejson) {
            console.log(scalejson);
            return scalejson;
        }
    })
    exports.run = function() {
        $form = $('.mark-from');
        var validator = new Validator({
            element: $form,
            autoSubmit: false,
            autoFocus: false,
            onFormValidated: function(error, results, $form) {
                if (error) {
                    return;
                }
                $.post($form.attr('action'), $form.serialize(), function(response) {
                    $('.question').html(response);
                });

            }
        });

        $(".pagination a").on('click', function(e) {
            e.preventDefault();
            $.get($(this).attr('href'), function(response) {
                $('.question').html(response);
            })
        })

        $(".question-tr").on('click', '.marker-preview', function() {
            $.get($(this).data('url'), function(response) {
                $('.modal').modal('show');
                $('.modal').html(response);

            })
        })



    }
});