// Remove member
function removeMember(memberId) {
  if (!confirm('Are you sure you want to remove this member?')) return;
  fetch('organization_manage.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `action=remove_member&member_id=${memberId}`
  }).then(() => location.reload());
}
