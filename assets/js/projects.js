document.addEventListener('click', (e) => {
  if (e.target.matches('[data-confirm-delete]')) {
    const name = e.target.getAttribute('data-name') || '';
    const input = prompt('Nhập "DELETE" để xác nhận xóa ' + name + ':');
    if (input !== 'DELETE') e.preventDefault();
  }
});
