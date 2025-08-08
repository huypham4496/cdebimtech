// Copy invite link
    document.addEventListener('click', e => {
      const btn = e.target.closest('.copy-btn');
      if (btn) {
        const inp = document.getElementById(btn.dataset.target);
        inp.select(); document.execCommand('copy');
        btn.innerHTML = '<i class="fas fa-check"></i>';
        setTimeout(() => btn.innerHTML = '<i class="fas fa-copy"></i>', 1500);
      }
    });

    // Open edit organization form
    function openEdit(id,name,abbr,address,dept){
      document.getElementById('orgAction').value='edit_org';
      document.getElementById('orgId').value=id;
      document.getElementById('name').value=name;
      document.getElementById('abbreviation').value=abbr;
      document.getElementById('address').value=address;
      document.getElementById('department').value=dept;
      document.getElementById('orgSubmit').innerHTML='<i class="fas fa-save"></i> Update Org';
      document.getElementById('orgCancel').style.display='inline-block';
    }
    function resetForm(){
      document.getElementById('orgAction').value='create_org';
      document.getElementById('orgForm').reset();
      document.getElementById('orgSubmit').innerHTML='<i class="fas fa-plus-circle"></i> Create Org';
      document.getElementById('orgCancel').style.display='none';
    }

    // Change role
    document.addEventListener('change', e => {
      if (e.target.matches('.role-select')) {
        const sel = e.target;
        fetch('organization_manage.php', {
          method:'POST',
          headers:{'Content-Type':'application/x-www-form-urlencoded'},
          body:`action=update_role&member_id=${sel.dataset.memberId}&role=${sel.value}`
        }).then(()=>sel.classList.add('updated'))
          .then(()=>setTimeout(()=>sel.classList.remove('updated'),800));
      }
    });

    // Open profile edit
    function openProfile(id,fn,ex,pos,dob,ht,rs,ph,mp){
      document.getElementById('profileForm').style.display='block';
      document.getElementById('profileMemberId').value=id;
      ['full_name','expertise','position','dob','hometown','residence','phone','monthly_performance']
        .forEach((f,i)=>document.getElementById(f).value=[fn,ex,pos,dob,ht,rs,ph,mp][i]);
    }
    function resetProfile(){
      document.getElementById('profileForm').style.display='none';
    }

    // Remove member
    function removeMember(id){
      if (!confirm('Remove this member?')) return;
      fetch('organization_manage.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`action=remove_member&member_id=${id}`
      }).then(()=>location.reload());
    }
