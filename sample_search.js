var Main={

};

var Samples={
   /**
    * Submits the parameters as defined by the user for processing
    */
   search: function(){
      var params, settings, reg = /^[A-Z0-9]{4,5}\s[0-9]$/i, errors = false;
         //check that the trays are in the right shape
         var i=0;
         $.each($('.tray_name'), function(){
            i++;this.value = this.value.trim().toUpperCase();
            if(this.value == undefined || this.value == '' || !reg.test(this.value)){
               alert("Please enter the tray format that we are expecting for tray "+i+"!\nThe format should be something like 'TAVQC 5' meaning that the tray name has the prefix TAVQC and 5 digits.");
               errors = true;
               return;
            }
         });
         if(errors) return;
         i=0;errors = false;
         $.each($('.tray_size'), function(){
            i++;this.value = this.value.trim();
            if(this.value==='undefined' || isNaN(this.value) || this.value===''){
               alert("Please enter a valid tray size for tray #"+i);
               return;
            }
         });
         if(errors) return;
         if(Main.ajaxParams==undefined) Main.ajaxParams={};
         Main.ajaxParams.result=undefined;
         Main.ajaxParams.div2Update='main_div';
         Main.ajaxParams.successMssg='Successfully updated.';
         notificationMessage({create:true, hide:false, updatetext:false, text:'Searching...'});
         params=$('#searchFormId').formSerialize();
         settings={type:"POST", url:'main.php?page=sort_aliquots', data:params, dataType:'text', success:Samples.updateInterface};
         $.ajax(settings);
         getObject('searchItemId').focus();
   },

   updateInterface: function(data){
   var message, err=true;
   var resp=data.split('$$');
   if(Main.ajaxParams.successMssg!=undefined) message=Main.ajaxParams.successMssg;
   else message='The changes have been successfully saved.';
   if(resp[0]=='error') message=resp[1];
   else if(resp[0]=='no_error'){
      if(Main.ajaxParams.div2Update) getObject(Main.ajaxParams.div2Update).innerHTML=resp[1];
      if(resp[2]!=undefined) $('#addinfoId').attr({innerHTML:resp[2]});
      err=false;
   }
   else if(resp[0].substr(0,2)=='-1') message=resp[0].substring(2,resp[0].length);
   else if(resp[0]=='new'){    //we have no error
      if(Main.ajaxParams.div2Update!=undefined) getObject(Main.ajaxParams.div2UpdateNew).innerHTML=resp[1];
      if(resp[2]!=undefined) $('#addinfoId').attr({innerHTML:resp[2]});
      err=false;
   }
   else{    //we have no error
      if(Main.ajaxParams.div2Update!=undefined) getObject(Main.ajaxParams.div2Update).innerHTML=resp[0];
      if(resp[2]!=undefined) $('#addinfoId').attr({innerHTML:resp[2]});
      err=false;
   }
   if(getObject('notification_box')!=undefined){
      notificationMessage({create:false, hide:true, updateText:true, text:message, error:err});
   }
   $('#searchItemId').focus();
},

   /**
    * Generates placeholder for defining the kind of trays that we are using
    */
   generateAliquots: function(){
   var count=$('#aliquot_number_id').val(), content='', reg;
   var parent_format = $('#parent_format').val().trim().toUpperCase(), aliquot_format = $('#aliquot_format').val().trim().toUpperCase();
   if(count==='undefined' || isNaN(count) || count===''){
      alert("Please enter a valid number of aliquots");
      $('#aliquot_number_id').focus();
      return;
   }
   else if('//'){

   }
   //check that the user has entered the correct aliquot and parent format
   reg = /^[A-Z0-9]{3,4}\s[0-9]$/i
   if(parent_format=='undefined' || parent_format=='' ||  !reg.test(parent_format)){
      alert("Please enter the parent format that we are expecting!\nThe format should be something like 'BSR 6' meaning that a parent has the prefix BSR and 6 digits.");
      $('#parent_format').focus();
      return;
   }
   if(aliquot_format=='undefined' || aliquot_format=='' || !reg.test(aliquot_format)){
      alert("Please enter the aliquot formt to expect.\nThe format should be something like 'AVAQ 5' meaning that each aliquot will have the prefix AVAQ and 5 digits.");
      $('#aliquot_format').focus();
      return;
   }
   //create the tray's info placeholders
   count=parseInt(count);
   content='<td colspan=6><table><tr><th>Tray label</th><th>Tray Description</th><th>Tray Size</th></tr>';
   for(var i=0; i<count; i++){
      content+="<tr><td>Tray "+(i+1)+":&nbsp;&nbsp;<input type='text' name='aliquot_settings[trays]["+i+"][name]' class='tray_name' size='20' /></td>"
         +"<td><input type='text' name='aliquot_settings[trays]["+i+"][descr]' size='20' /></td>"
         +"<td><input type='text' name='aliquot_settings[trays]["+i+"][size]' size='5' /></td></tr>";
   }
   content+='</table></td>';
   $('#aliquotNos').attr({innerHTML: content});
   $('.tray_name')[0].focus();
},

   /**
    * When we receive a key 13, call the search function
    */
   simulateEnterButton: function(e){
      if(e.keyCode==13) Samples.search();
   },

   ukarabati: function(aliquot, action){
      var res, message, sender, parent, tray, position, edited_aliquot;
      if(action=='delete') message = 'Are you sure you want to delete "'+aliquot+'"?';
      else if(action=='edit') message = 'Are you sure you want to edit the tray settings for "'+aliquot+'"?';
      else return;   //unknown option
      res = confirm(message);
      if(res==false) return;
      //get the sender
      $.each($('#aliquots td'), function(){
         if(this.innerHTML == aliquot){
            sender = this;
            return;
         }
      });
      parent = sender.parentNode;
      if(action=='edit'){
         tray = parent.childNodes[2].innerHTML;
         position = parent.childNodes[3].innerHTML;
         parent.childNodes[2].innerHTML = "<input type='text' name='edited_tray' value='"+tray+"' />";   //the tray
         parent.childNodes[3].innerHTML = "<input type='text' name='edited_position' value='"+position+"' />";   //tray position
         parent.childNodes[4].innerHTML = "<a href='javascript:;' onClick='Samples.saveEdits(\"aliquot\");'>Save Changes</a>";   //save link
         $('[name=edited_tray').focus();
         edited_aliquot = document.createElement('span');
         edited_aliquot.innerHTML = "<input type='hidden' name='aliquot2edit' value='"+aliquot+"' />";
         parent.appendChild(edited_aliquot);
      }
      else{
         var form_action;
         edited_aliquot = document.createElement('span');
         edited_aliquot.innerHTML = "<input type='hidden' name='aliquot2delete' value='"+aliquot+"' />";
         parent.appendChild(edited_aliquot);
         form_action = $('#searchFormId')[0].action;
         $('#searchFormId')[0].action = form_action.replace(/\?page=.+/, '?page=delete_aliquot');
         $('[name=searchItem]').val($('[name=parent]').val());
         document.forms["searchFormId"].submit();
      }
   },

   saveEdits: function(aliquot){
      //lets do the saving. replace the default action with update_positions and we hope all will be well
      var form_action;
      form_action = $('#searchFormId')[0].action;
      $('#searchFormId')[0].action = form_action.replace(/\?page=.+/, '?page=update_positions');
      $('[name=searchItem]').val($('[name=parent]').val());
      document.forms["searchFormId"].submit();
   }
};