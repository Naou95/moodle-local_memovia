// This file is part of Moodle - https://moodle.org/ (local_memovia, GNU GPL v3 or later).
// Places the "Open with MEMOVIA" buttons next to the adapted files: course page (resources) and folder page (each file).
// Plain script (no AMD build): data comes from #local-memovia-data, written by hook_callbacks::before_footer.
(function() {
    var node = document.getElementById('local-memovia-data');
    if (!node) {
        return;
    }
    var data;
    try {
        data = JSON.parse(node.textContent);
    } catch (e) {
        return;
    }
    var openUrl = function(cmid, key) {
        return data.openurl + '?cmid=' + encodeURIComponent(cmid) + '&key=' + encodeURIComponent(key) +
            '&sesskey=' + encodeURIComponent(data.sesskey);
    };
    var widget = function(cmid, key, info) {
        var el;
        if (info.status === 'ready') {
            el = document.createElement('a');
            el.className = 'local-memovia-open btn btn-sm btn-outline-primary';
            el.href = openUrl(cmid, key);
            el.target = '_blank';
            el.rel = 'noopener';
            el.textContent = data.labels.open;
            el.title = data.labels.title;
        } else {
            el = document.createElement('span');
            el.className = 'local-memovia-badge badge bg-secondary';
            el.textContent = (info.status === 'pending' || info.status === 'processing') ? data.labels.inprogress : data.labels.notadapted;
            if (info.reason) {
                el.title = info.reason;
                el.textContent += ': ' + info.reason;
            }
        }
        return el;
    };
    if (data.page === 'course') {
        Object.keys(data.files).forEach(function(cmid) {
            var info = data.files[cmid][''];
            var li = document.querySelector('li.activity[data-id="' + cmid + '"], #module-' + cmid);
            var anchor = li && (li.querySelector('.activityname') || li.querySelector('.activity-instance'));
            if (info && anchor && !li.querySelector('.local-memovia-open, .local-memovia-badge')) {
                anchor.parentNode.insertBefore(widget(cmid, '', info), anchor.nextSibling);
            }
        });
    } else {
        var cmid = Object.keys(data.files)[0];
        var files = cmid ? data.files[cmid] : {};
        document.querySelectorAll('a[href*="/mod_folder/content/"]').forEach(function(a) {
            var m = /\/mod_folder\/content\/\d+(\/.*)$/.exec(new URL(a.href).pathname);
            var key = m ? decodeURIComponent(m[1]) : null;
            if (key && files[key] && !a.parentNode.querySelector('.local-memovia-open, .local-memovia-badge')) {
                a.parentNode.insertBefore(widget(cmid, key, files[key]), a.nextSibling);
            }
        });
    }
    if (data.banner) {
        var main = document.querySelector('#region-main [role="main"], #region-main, [role="main"]');
        if (main) {
            var b = document.createElement('div');
            b.className = 'local-memovia-banner alert alert-info';
            b.setAttribute('role', 'status');
            b.textContent = data.banner;
            main.insertBefore(b, main.firstChild);
        }
    }
})();
