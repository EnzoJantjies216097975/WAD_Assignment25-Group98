// assets/js/timetable.js - Main drag and drop functionality for NUST Timetable Manager

$(document).ready(function() {
    // Initialize timetable data structure
    let timetableData = {};
    let draggedElement = null;
    let originalParent = null;
    
    // Initialize draggable for course cards
    $('.draggable-course').draggable({
        helper: 'clone',
        cursor: 'move',
        zIndex: 1000,
        revert: 'invalid',
        start: function(event, ui) {
            draggedElement = $(this);
            $(this).addClass('dragging');
            ui.helper.css({
                'width': $(this).width(),
                'opacity': '0.8',
                'box-shadow': '0 5px 15px rgba(0,0,0,0.3)'
            });
        },
        stop: function(event, ui) {
            $(this).removeClass('dragging');
        }
    });
    
    // Initialize droppable for timetable slots
    $('.droppable-slot').droppable({
        accept: '.draggable-course, .scheduled-class',
        hoverClass: 'droppable',
        drop: function(event, ui) {
            handleDrop($(this), ui.draggable);
        }
    });
    
    // Handle drop event
    function handleDrop(targetSlot, draggedItem) {
        const slotDay = targetSlot.data('day');
        const slotTime = targetSlot.data('time');
        const slotType = targetSlot.data('slot-type');
        
        // Get course data
        const courseId = draggedItem.data('course-id');
        const courseCode = draggedItem.data('course-code');
        const courseName = draggedItem.data('course-name');
        const classType = draggedItem.data('class-type');
        const duration = parseInt(draggedItem.data('duration'));
        const lecturer = draggedItem.data('lecturer');
        const color = draggedItem.data('color');
        
        // Check if slot is already occupied
        if (targetSlot.hasClass('occupied')) {
            showAlert('This time slot is already occupied!', 'error');
            return false;
        }
        
        // Check for conflicts
        if (!checkConflicts(slotDay, slotTime, duration, courseId)) {
            return false;
        }
        
        // For practical classes (2 hours), check if next slot is available
        if (duration === 2) {
            const nextSlot = getNextSlot(slotDay, slotTime);
            if (!nextSlot || nextSlot.hasClass('occupied') || nextSlot.hasClass('lunch-break')) {
                showAlert('Not enough consecutive slots for a 2-hour practical class!', 'error');
                return false;
            }
        }
        
        // Show modal for venue selection
        showClassModal({
            courseId: courseId,
            courseCode: courseCode,
            courseName: courseName,
            classType: classType,
            duration: duration,
            lecturer: lecturer,
            color: color,
            slotDay: slotDay,
            slotTime: slotTime,
            targetSlot: targetSlot
        });
    }
    
    // Show class details modal
    function showClassModal(classData) {
        $('#modalCourseName').val(classData.courseCode + ' - ' + classData.courseName);
        $('#modalClassType').val(classData.classType.charAt(0).toUpperCase() + classData.classType.slice(1));
        $('#modalCourseId').val(classData.courseId);
        $('#modalSlotDay').val(classData.slotDay);
        $('#modalSlotTime').val(classData.slotTime);
        
        // Store class data for later use
        $('#classModal').data('classData', classData);
        
        // Show modal
        $('#classModal').addClass('show');
    }
    
    // Confirm adding class to timetable
    $('#confirmAddClass').click(function() {
        const classData = $('#classModal').data('classData');
        const venue = $('#modalVenue option:selected').text();
        const venueId = $('#modalVenue').val();
        const notes = $('#modalNotes').val();
        
        if (!venueId) {
            alert('Please select a venue for the class');
            return;
        }
        
        // Add class to timetable
        addClassToTimetable({
            ...classData,
            venue: venue,
            venueId: venueId,
            notes: notes
        });
        
        // Close modal
        $('#classModal').removeClass('show');
        $('#classDetailsForm')[0].reset();
    });
    
    // Add class to timetable
    function addClassToTimetable(classData) {
        const targetSlot = classData.targetSlot;
        const duration = classData.duration;
        
        // Create class element
        const classElement = $('<div>')
            .addClass('scheduled-class')
            .addClass(classData.classType === 'practical' ? 'practical' : 'theory')
            .attr({
                'data-course-id': classData.courseId,
                'data-class-type': classData.classType,
                'data-duration': duration
            })
            .css('background', `linear-gradient(135deg, ${classData.color}, ${classData.color}dd)`)
            .html(`
                <span class="remove-btn">×</span>
                <span class="class-type">${classData.classType === 'practical' ? 'P' : 'T'}</span>
                <div class="class-code">${classData.courseCode}</div>
                <div class="class-room">${classData.venue}</div>
                <div style="font-size: 10px; opacity: 0.9;">${classData.lecturer}</div>
            `);
        
        // Add to slot
        targetSlot.empty().append(classElement).addClass('occupied');
        
        // For practical classes, occupy next slot too
        if (duration === 2) {
            const nextSlot = getNextSlot(classData.slotDay, classData.slotTime);
            if (nextSlot) {
                const extendedElement = classElement.clone();
                extendedElement.find('.class-type').text('P (cont.)');
                nextSlot.empty().append(extendedElement).addClass('occupied');
            }
        }
        
        // Store in timetable data
        const key = `${classData.slotDay}-${classData.slotTime}`;
        timetableData[key] = classData;
        
        // Make the scheduled class draggable for rearrangement
        makeScheduledClassDraggable(classElement);
        
        showAlert('Class added successfully!', 'success');
    }
    
    // Make scheduled classes draggable
    function makeScheduledClassDraggable(element) {
        element.draggable({
            helper: 'clone',
            cursor: 'move',
            zIndex: 1000,
            revert: 'invalid',
            start: function(event, ui) {
                originalParent = $(this).parent();
                $(this).css('opacity', '0.5');
            },
            stop: function(event, ui) {
                $(this).css('opacity', '1');
            }
        });
    }
    
    // Remove class from timetable
    $(document).on('click', '.remove-btn', function(e) {
        e.stopPropagation();
        const classElement = $(this).closest('.scheduled-class');
        const slot = classElement.parent();
        const duration = parseInt(classElement.data('duration'));
        const slotDay = slot.data('day');
        const slotTime = slot.data('time');
        
        if (confirm('Remove this class from the timetable?')) {
            // Remove from current slot
            slot.removeClass('occupied').empty();
            
            // If practical (2 hours), also clear next slot
            if (duration === 2) {
                const nextSlot = getNextSlot(slotDay, slotTime);
                if (nextSlot) {
                    nextSlot.removeClass('occupied').empty();
                }
            }
            
            // Remove from data
            const key = `${slotDay}-${slotTime}`;
            delete timetableData[key];
            
            showAlert('Class removed from timetable', 'info');
        }
    });
    
    // Get next time slot
    function getNextSlot(day, time) {
        const times = ['07:30', '08:30', '09:30', '10:30', '11:30', '12:30', '14:00', '15:00', '17:15', '18:40', '20:00'];
        const currentIndex = times.indexOf(time);
        
        if (currentIndex !== -1 && currentIndex < times.length - 1) {
            const nextTime = times[currentIndex + 1];
            return $(`.droppable-slot[data-day="${day}"][data-time="${nextTime}"]`);
        }
        return null;
    }
    
    // Check for conflicts
    function checkConflicts(day, time, duration, courseId) {
        // Check if same course already scheduled at different time
        for (let key in timetableData) {
            if (timetableData[key].courseId === courseId) {
                showAlert('This course is already scheduled in your timetable!', 'warning');
                return false;
            }
        }
        return true;
    }
    
    // Course search functionality
    $('#courseSearch').on('keyup', function() {
        const searchTerm = $(this).val().toLowerCase();
        
        $('.course-card').each(function() {
            const courseCode = $(this).data('course-code').toLowerCase();
            const courseName = $(this).data('course-name').toLowerCase();
            const lecturer = $(this).data('lecturer').toLowerCase();
            
            if (courseCode.includes(searchTerm) || 
                courseName.includes(searchTerm) || 
                lecturer.includes(searchTerm)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });
    
    // Filter buttons
    $('.filter-btn').click(function() {
        $('.filter-btn').removeClass('active');
        $(this).addClass('active');
        
        const filterType = $(this).data('type');
        
        if (filterType === 'all') {
            $('.course-card').show();
        } else {
            $('.course-card').each(function() {
                if ($(this).data('class-type') === filterType) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        }
    });
    
    // Clear all button
    $('#clearBtn').click(function() {
        if (confirm('Are you sure you want to clear the entire timetable?')) {
            $('.slot-cell.occupied').removeClass('occupied').empty();
            timetableData = {};
            showAlert('Timetable cleared', 'info');
        }
    });
    
    // Save timetable
    $('#saveBtn').click(function() {
        const scheduleName = $('#scheduleName').val().trim();
        
        if (!scheduleName) {
            showAlert('Please enter a name for your timetable', 'warning');
            return;
        }
        
        if (Object.keys(timetableData).length === 0) {
            showAlert('Your timetable is empty. Add some classes first!', 'warning');
            return;
        }
        
        // Prepare data for saving
        const saveData = {
            name: scheduleName,
            semester: 2,
            year: 2025,
            items: []
        };
        
        // Convert timetable data to save format
        for (let key in timetableData) {
            const classData = timetableData[key];
            saveData.items.push({
                course_id: classData.courseId,
                day: classData.slotDay,
                time: classData.slotTime,
                class_type: classData.classType,
                duration: classData.duration,
                venue_id: classData.venueId,
                lecturer: classData.lecturer,
                notes: classData.notes || ''
            });
        }
        
        // Save via AJAX
        $.ajax({
            url: '../api/save-schedule.php',
            method: 'POST',
            data: JSON.stringify(saveData),
            contentType: 'application/json',
            success: function(response) {
                if (response.success) {
                    showAlert('Timetable saved successfully!', 'success');
                    setTimeout(() => {
                        window.location.href = 'schedules.php';
                    }, 2000);
                } else {
                    showAlert('Error saving timetable: ' + response.message, 'error');
                }
            },
            error: function() {
                showAlert('Error connecting to server', 'error');
            }
        });
    });
    
    // Export PDF functionality
    $('#exportBtn').click(function() {
        const scheduleName = $('#scheduleName').val() || 'My Timetable';
        
        if (Object.keys(timetableData).length === 0) {
            showAlert('Your timetable is empty. Add some classes first!', 'warning');
            return;
        }
        
        // This would typically generate a PDF
        // For now, we'll prepare the data and show a message
        showAlert('Export functionality will be implemented soon!', 'info');
    });
    
    // Modal close handlers
    $('.modal-close, #cancelAddClass').click(function() {
        $('#classModal').removeClass('show');
        $('#classDetailsForm')[0].reset();
    });
    
    // Click outside modal to close
    $(window).click(function(e) {
        if ($(e.target).hasClass('modal')) {
            $('.modal').removeClass('show');
            $('#classDetailsForm')[0].reset();
        }
    });
    
    // Alert function
    function showAlert(message, type) {
        // Create alert element
        const alert = $('<div>')
            .addClass('alert-toast')
            .addClass('alert-' + type)
            .text(message)
            .css({
                'position': 'fixed',
                'top': '20px',
                'right': '20px',
                'padding': '15px 20px',
                'border-radius': '8px',
                'background': type === 'success' ? '#2ECC71' : 
                             type === 'error' ? '#E74C3C' :
                             type === 'warning' ? '#F39C12' : '#3498DB',
                'color': 'white',
                'font-weight': '600',
                'z-index': '9999',
                'box-shadow': '0 4px 12px rgba(0,0,0,0.15)',
                'display': 'none'
            });
        
        $('body').append(alert);
        alert.fadeIn(300);
        
        setTimeout(() => {
            alert.fadeOut(300, function() {
                $(this).remove();
            });
        }, 3000);
    }
    
    // Load existing schedule if editing
    const urlParams = new URLSearchParams(window.location.search);
    const scheduleId = urlParams.get('id');
    
    if (scheduleId) {
        loadSchedule(scheduleId);
    }
    
    // Load schedule function
    function loadSchedule(scheduleId) {
        $.ajax({
            url: '../api/load-schedule.php',
            method: 'GET',
            data: { id: scheduleId },
            success: function(response) {
                if (response.success) {
                    // Populate timetable with loaded data
                    $('#scheduleName').val(response.data.name);
                    
                    response.data.items.forEach(item => {
                        // Find the slot and add the class
                        const slot = $(`.droppable-slot[data-day="${item.day}"][data-time="${item.time}"]`);
                        if (slot.length) {
                            // Recreate the class element
                            // Implementation would go here
                        }
                    });
                }
            }
        });
    }
});