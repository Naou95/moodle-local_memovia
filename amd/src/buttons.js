// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Places the "Open with MEMOVIA" buttons next to the adapted files: course page (resources) and folder page (each file).
 * Data comes from #local-memovia-data, written by hook_callbacks::before_footer.
 *
 * @module     local_memovia/buttons
 * @copyright  2026 MEMOVIA SAS
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Button (adapted file) or status badge (teachers only).
 *
 * @param {Object} data page data
 * @param {String} cmid course module id
 * @param {String} key file key ('' for a resource)
 * @param {Object} info {status, reason}
 * @returns {HTMLElement}
 */
const widget = (data, cmid, key, info) => {
    let el;
    if (info.status === 'ready') {
        el = document.createElement('a');
        el.className = 'local-memovia-open btn btn-sm btn-outline-primary';
        el.href = data.openurl + '?cmid=' + encodeURIComponent(cmid) + '&key=' + encodeURIComponent(key) +
            '&sesskey=' + encodeURIComponent(data.sesskey);
        el.target = '_blank';
        el.rel = 'noopener';
        el.textContent = data.labels.open;
        el.title = data.labels.title;
    } else {
        el = document.createElement('span');
        el.className = 'local-memovia-badge badge bg-secondary';
        el.textContent = (info.status === 'pending' || info.status === 'processing') ? data.labels.inprogress
            : data.labels.notadapted;
        if (info.reason) {
            el.title = info.reason;
            el.textContent += ': ' + info.reason;
        }
    }
    return el;
};

export const init = () => {
    const node = document.getElementById('local-memovia-data');
    if (!node) {
        return;
    }
    let data;
    try {
        data = JSON.parse(node.textContent);
    } catch (e) {
        return;
    }
    if (data.page === 'course') {
        Object.keys(data.files).forEach((cmid) => {
            const info = data.files[cmid][''];
            const li = document.querySelector('li.activity[data-id="' + cmid + '"], #module-' + cmid);
            const anchor = li && (li.querySelector('.activityname') || li.querySelector('.activity-instance'));
            if (info && anchor && !li.querySelector('.local-memovia-open, .local-memovia-badge')) {
                anchor.parentNode.insertBefore(widget(data, cmid, '', info), anchor.nextSibling);
            }
        });
    } else {
        const cmid = Object.keys(data.files)[0];
        const files = cmid ? data.files[cmid] : {};
        document.querySelectorAll('a[href*="/mod_folder/content/"]').forEach((a) => {
            const m = /\/mod_folder\/content\/\d+(\/.*)$/.exec(new URL(a.href).pathname);
            const key = m ? decodeURIComponent(m[1]) : null;
            if (key && files[key] && !a.parentNode.querySelector('.local-memovia-open, .local-memovia-badge')) {
                a.parentNode.insertBefore(widget(data, cmid, key, files[key]), a.nextSibling);
            }
        });
    }
    if (data.banner) {
        const main = document.querySelector('#region-main [role="main"], #region-main, [role="main"]');
        if (main) {
            const b = document.createElement('div');
            b.className = 'local-memovia-banner alert alert-info';
            b.setAttribute('role', 'status');
            b.textContent = data.banner;
            main.insertBefore(b, main.firstChild);
        }
    }
};
