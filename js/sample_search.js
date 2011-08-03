
/**
* Copyright 2011 ILRI
* 
* This file is part of ukasimu.
* 
* ukasimu is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
* 
* ukasimu is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
* 
* You should have received a copy of the GNU General Public License
* along with ukasimu.  If not, see <http://www.gnu.org/licenses/>.
*/

var Main={

};

var Samples = {
   defaultTrayData: new Array(
      {tray: 'TAVQC 5', description: 'ICIPE', size: 81},
      {tray: 'TAVQA 5', description: 'DVS', size: 81},
      {tray: 'TAVQL 5', description: 'ILRI', size: 100}
   ),
      
   /**
    * Submits the parameters as defined by the user for processing
    */
   search: function(){
      var params, settings, reg = /^[A-Z0-9]{4,5}\s[0-9]$/i, errors = false;
         //check that the trays are in the right shape
         var i=0;
         $.each($('.tray_name'), function(){
            i++;
            this.value = this.value.trim().toUpperCase();
            if(this.value == undefined || this.value == '' || !reg.test(this.value)){
               alert("Please enter the tray format that we are expecting for tray "+i+"!\nThe format should be something like 'TAVQC 5' meaning that the tray name has the prefix TAVQC and 5 digits.");
               errors = true;
               return;
            }
         });
         if(errors) return;
         i=0;errors = false;
         $.each($('.tray_size'), function(){
            i++;
            this.value = this.value.trim();
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
         params = $('#searchFormId').formSerialize();
         if(Samples.parent != undefined) params += '&parent='+$.toJSON(Samples.parent);
         if(Samples.settings != undefined) params += '&settings='+$.toJSON(Samples.settings);
         if(Samples.aliquot2save != undefined) params += '&aliquot2save='+$.toJSON(Samples.aliquot2save);
         if($('#nextPositionId').length != 0) params += '&nextPosition='+$('#nextPositionId').val();
         if($('#nextTrayId').length != 0) params += '&nextTray='+$('#nextTrayId').val();
         if($('#searchItemId').length != 0) params += '&searchItem='+$('#searchItemId').val();
         $.ajax({type:"POST", url:'mod_ajax_calls.php?page=sort_aliquots', data:params, dataType:'html', success:Samples.updateInterface});
         $('#searchItemId').focus();
   },

   updateInterface: function(data){
      var message, err = true;
      if(data.substr(0,2) == '-1') message = data.substring(2,data.length);
      else{
         if(Main.ajaxParams.successMssg != undefined) message = Main.ajaxParams.successMssg;
         else message = 'The changes have been successfully saved.';
         err = false;
         if(Main.ajaxParams.div2Update != undefined) $('#'+Main.ajaxParams.div2Update).html(data);
         
      }
      
      if($('#notification_box')!=undefined){
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
         content+="<tr><td>Tray "+(i+1)+":&nbsp;&nbsp;<input type='text' name='aliquot_settings[trays]["+i+"][name]' size='15' value='"+Samples.defaultTrayData[i].tray+"' class='tray_name' /></td>"
            +"<td><input type='text' name='aliquot_settings[trays]["+i+"][descr]' size='20' value='"+Samples.defaultTrayData[i].description+"' /></td>"
            +"<td><input type='text' name='aliquot_settings[trays]["+i+"][size]' size='5' value='"+Samples.defaultTrayData[i].size+"' /></td></tr>";
      }
      content+='</table></td>';
      $('#aliquotNos').html(content);
      $('.tray_name')[0].focus();
      $('[name=find]').bind('click', Samples.search);
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
   },
   
   displayExtraInfo: function(timestamp, lat, longitude, collector, comments, clinical, animal){
      $('#timestamp').innerHTML="<b>"+timestamp+"</b>";
      $('#animal').innerHTML="<b>"+animal+"</b>";
      $('#lat').innerHTML="<b>"+lat+"</b>";
      $('#long').innerHTML="<b>"+longitude+"</b>";
      $('#clinical').innerHTML="<b>"+clinical+"</b>";
      $('#collector').innerHTML="<b>"+collector+"</b>";
      $('#comments').innerHTML="<b>"+comments+"</b>";
   }
};

if($('#searchItemId').length != 0){
   $('#searchItemId').focus();
}