<?php
// Run: php tests/friend-links.php. No WordPress database or network required.
define('ABSPATH', __DIR__ . '/');
function add_action(...$args) {}
function get_option($name, $default = false) { return $GLOBALS['options'][$name] ?? $default; }
function update_option($name, $value) { $GLOBALS['options'][$name] = $value; }
function current_user_can($cap) { return $GLOBALS['allowed']; }
function check_ajax_referer(...$args) { if (!$GLOBALS['nonce']) throw new RuntimeException('nonce'); }
function sanitize_key($s) { return $s; }
function absint($n) { return abs((int) $n); }
function wp_attachment_is_image($id) { return $id === 99; }
function wp_get_attachment_image_url($id, $size) { return 'image-' . $id; }
function wp_send_json_error($data, $status) { throw new RuntimeException('error:' . $status); }
function wp_send_json_success($data = null) { throw new RuntimeException('success'); }
function wp_cache_delete(...$args) {}
function wp_remote_get(...$args) {
    // Simulate another request changing the list while a screenshot fetch is pending.
    $GLOBALS['options']['sphotography_friend_links'] = $GLOBALS['during_fetch'];
    return false;
}
function is_wp_error($value) { return $value === false; }
function wp_schedule_single_event(...$args) {}
require __DIR__ . '/../Sphotography/inc/friend-links.php';
function check($condition, $label) { if (!$condition) throw new RuntimeException($label); }
function request($data, $expected) {
    $_POST = $data;
    try { sphotography_ajax_friend_edit(); } catch (RuntimeException $e) {
        check($e->getMessage() === $expected, $e->getMessage() . ' != ' . $expected);
        return;
    }
    throw new RuntimeException('Expected a response');
}
$GLOBALS['allowed'] = true; $GLOBALS['nonce'] = true;
$links = array(
    array('id'=>1, 'pinned'=>0, 'added'=>10, 'thumb_id'=>0, 'name'=>'one', 'url'=>'https://one.test'),
    array('id'=>2, 'pinned'=>1, 'added'=>20, 'thumb_id'=>0, 'name'=>'two', 'url'=>'https://two.test'),
    array('id'=>3, 'pinned'=>0, 'added'=>30, 'thumb_id'=>0, 'name'=>'three', 'url'=>'https://three.test'),
);
$sorted = $links; sphotography_sort_friend_links($sorted);
check(array_column($sorted, 'id') === array(2,1,3), 'Legacy pin ordering');
$GLOBALS['options']['sphotography_friend_links'] = $links;
request(array('operation'=>'reorder', 'ids'=>array(3,2,1)), 'success');
$sorted = sphotography_get_friend_links(); sphotography_sort_friend_links($sorted);
check(array_column($sorted,'id') === array(3,2,1), 'Persisted manual order');
check(array_sum(array_column($sorted,'pinned')) === 0, 'Manual order clears pins');
$before = sphotography_get_friend_links();
request(array('operation'=>'reorder', 'ids'=>array(1,1,2)), 'error:409');
check($before === sphotography_get_friend_links(), 'Invalid order must not mutate data');
request(array('operation'=>'thumbnail','id'=>1,'thumb_id'=>98), 'error:400');
request(array('operation'=>'thumbnail','id'=>20,'thumb_id'=>99), 'error:404');
request(array('operation'=>'thumbnail','id'=>1,'thumb_id'=>99), 'success');
check(sphotography_get_friend_links()[0]['thumb_id'] === 99, 'Thumbnail persisted');
$GLOBALS['allowed'] = false;
request(array('operation'=>'reorder','ids'=>array(1,2,3)), 'error:403');
$GLOBALS['allowed'] = true; $GLOBALS['nonce'] = false;
request(array('operation'=>'reorder','ids'=>array(1,2,3)), 'nonce');
$GLOBALS['nonce'] = true;
$GLOBALS['during_fetch'] = sphotography_get_friend_links();
$GLOBALS['options']['sphotography_friend_links'] = $links;
sphotography_fetch_friend_meta_handler(1);
check(sphotography_get_friend_links() === $GLOBALS['during_fetch'], 'Slow fetch must preserve thumbnail and manual order');
$GLOBALS['options']['sphotography_friend_links'] = $links;
$GLOBALS['during_fetch'] = array();
sphotography_fetch_friend_meta_handler(1);
check(sphotography_get_friend_links() === array(), 'Slow fetch must not resurrect deleted links');
echo "PASS: legacy/manual ordering, invalid IDs, thumbnail replacement, permissions, nonce, fetch races\n";
