
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
      var params;
      //lets check for errors
      if(Samples.validateInputs() == 1) return;
      
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

   /**
    * Updates the interface with some data from the server
    */
   updateInterface: function(data){
      var message, err = true;
      if(data.substr(0,2) == '-1') message = data.substring(2,data.length);
      else{
         if(Main.ajaxParams.successMssg != undefined) message = Main.ajaxParams.successMssg;
         else message = 'The changes have been successfully saved.';
         err = false;
         if(Main.ajaxParams.div2Update != undefined) $('#'+Main.ajaxParams.div2Update).html(data);
      }
      
      if($('#notification_box') != undefined){
         notificationMessage({create:false, hide:true, updateText:true, text:message, error:err});
      }
      $('#searchItemId').focus();
   },

   /**
    * Generates placeholder for defining the kind of trays that we are using
    */
   generateAliquots: function(){
      var count=$('#aliquot_number_id').val(), content='';
      //lets check for errors
      if(Samples.validateInputs() == 1) return;
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
      $('[name=find]').unbind('click');
      $('[name=find]').bind('click', Samples.search);
   },
   
   /**
    * Validates all the input data being received from the user
    */
   validateInputs: function(){
      //lets validate the number of aliquots entered
      var i=0, errors = false, reg;
      if($('#aliquot_number_id').length != 0){
         var count=$('#aliquot_number_id').val()
         if(count==='undefined' || isNaN(count) || count===''){
            alert("Please enter a valid number of aliquots");
            $('#aliquot_number_id').focus();
            return 1;
         }
      }
      
      //lets validate the parent format entered
      if($('#parent_format').length != 0){
         reg = /^[A-Z0-9]{3,4}\s+[0-9](\-[0-9])?$/i
         var parent_format = $('#parent_format').val().trim().toUpperCase()
         if(parent_format=='undefined' || parent_format=='' ||  !reg.test(parent_format)){
            alert("Please enter the parent format that we are expecting!\nThe format should be something like 'BSR 6' meaning that a parent has the prefix BSR and 6 digits.");
            $('#parent_format').focus();
            return 1;
         }
      }
      
      //lets validate the aliquot format
      if($('#aliquot_format').length != 0){
         reg = /^[A-Z0-9]{3,4}\s+[0-9]((\-[0-9])?((\s+)?,(\s+)?[0-9])?)?$/i
         var aliquot_format = $('#aliquot_format').val().trim().toUpperCase();
         if(aliquot_format=='undefined' || aliquot_format=='' || !reg.test(aliquot_format)){
            var mssg = "Please enter the aliquot format to expect. The format should be something like\nAVAQ 5 ";
            mssg += "meaning that each aliquot will have the prefix AVAQ and 5 digits, or\nAVAQ 5-7 meaning each aliquot ";
            mssg += "has a prefix of AVAQ and a suffix of 5 to 7 digits or\nAVAQ 5-7,7 meaning either a AVAQ 5-7 ";
            mssg += "barcode or a barcode with just 7 digits.";
            alert(mssg);
            $('#aliquot_format').focus();
            return 1;
         }
      }
      
      //trays
      if($('.tray_name').length != 0){
         reg = /^[A-Z0-9]{4,5}\s[0-9]$/i;errors = false;
         $.each($('.tray_name'), function(){
            i++;
            this.value = this.value.trim().toUpperCase();
            if(this.value == undefined || this.value == '' || !reg.test(this.value)){
               alert("Please enter the tray format that we are expecting for tray "+i+"!\nThe format should be something like 'TAVQC 5' meaning that the tray name has the prefix TAVQC and 5 digits.");
               errors = true;
               return;
            }
         });
         if(errors) return 1;
      }
      
      //tray sizes
      if($('.tray_size').length != 0){
         i=0;errors = false;
         $.each($('.tray_size'), function(){
            i++;
            this.value = this.value.trim();
            if(this.value==='undefined' || isNaN(this.value) || this.value===''){
               alert("Please enter a valid tray size for tray #"+i);
               errors = true;
               return;
            }
         });
         if(errors) return 1;
      }
      
      //edited tray label
      if($('[name=edited_tray]').length != 0){
         var tray = $('[name=edited_tray]').val().toUpperCase();
         reg = /^[A-Z]{5}[0-9]{5}$/i;
         if(tray == undefined || tray == '' || !reg.test(tray)){
            alert("Error, the tray label entered is incorrect. Expecting the label should be something like 'TAVQC00032'.");
            return 1;
         }
      }
      
      //edited aliquot position
      if($('[name=edited_position]').length != 0){
         var pos = $('[name=edited_position]').val();
         reg = /^[0-9]{1,3}$/i;
         if(pos == undefined || pos == '' || !reg.test(pos) || pos == 0 || pos > 100){
            alert("Error, the new position of the aliquot is incorrect. It should be a number not equal to 0 and not greater than 100.");
            return 1;
         }
      }
      return 0;
   },

   /**
    * When we receive a key 13, call the search function
    */
   simulateEnterButton: function(e){
      if(e.keyCode==13) Samples.search();
   },

   ukarabati: function(aliquot, action){
      //check that we are not editing another aliquot first
      if($('[name=edited_tray]').length != 0){
         alert('Please finish editing '+$('[name=aliquot2edit]').val()+' first before editing this aliquot');
         return;
      }
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
         Main.prevTray = tray; Main.prevPos = position;
         parent.childNodes[2].innerHTML = "<input type='text' name='edited_tray' value='"+tray+"' />";   //the tray
         parent.childNodes[3].innerHTML = "<input type='text' name='edited_position' value='"+position+"' />";   //tray position
         parent.childNodes[4].innerHTML = "<a href='javascript:;' onClick='Samples.saveEdits();'>Save</a>\n\
         <a href='javascript:;' onClick='Samples.cancelEdits();'>Cancel</a><input type='hidden' name='aliquot2edit' value='"+aliquot+"' />";   //save and cancel links
         $('[name=edited_tray').focus();
      }
      else{
         Main.parent2delete = parent;
         //lets do the saving. replace the default action with update_positions and we hope all will be well
         var params;
         params = 'action=delete&aliquot='+encodeURIComponent(aliquot);
         $.ajax({type:"POST", url:'mod_ajax_calls.php?page=update_positions', data:params, dataType:'json', success:Samples.updateDeletes});
      }
   },

   /**
    * Cancels the editing process of the current samples
    */
   cancelEdits: function(){
      var aliquot = $('[name=aliquot2edit]').val();
      var parent = $('[name=edited_position]')[0].parentNode.parentNode;
      parent.childNodes[2].innerHTML = Main.prevTray;
      parent.childNodes[3].innerHTML = Main.prevPos;
      parent.childNodes[4].innerHTML = "<a href='javascript:;' onClick='Samples.ukarabati(\""+aliquot+"\", \"edit\")'>Edit</a>\n\
      <a href='javascript:;' onClick='Samples.ukarabati(\""+aliquot+"\", \"delete\")'>Delete</a>";   //restore the links
      $('#searchItemId').focus();
   },

   /**
    * Saves the new location for the aliquots being edited. Will check that everythings is in place before sending the data to the server
    */
   saveEdits: function(){
      //do the preliminary checks for the trays
      if(Samples.validateInputs() == 1) return;
      //lets do the saving. replace the default action with update_positions and we hope all will be well
      var params, tray = $('[name=edited_tray]').val(), pos = $('[name=edited_position]').val(), aliquot = $('[name=aliquot2edit]').val();
      params = 'action=edit&tray='+encodeURIComponent(tray)+'&position='+encodeURIComponent(pos)+'&aliquot='+encodeURIComponent(aliquot);
      Main.editedPos = pos; Main.editedTray = tray;
      $.ajax({type:"POST", url:'mod_ajax_calls.php?page=update_positions', data:params, dataType:'json', success:Samples.updateEdits});
   },
   
   /**
    * Updates the interface depending on whether the aliquots were correctly edited or not
    */
   updateEdits: function(data){
      var message, parent = $('[name=edited_position]')[0].parentNode.parentNode, aliquot = $('[name=aliquot2edit]').val();
      if(data.error == undefined){
         parent.childNodes[2].innerHTML = Main.editedTray;
         parent.childNodes[3].innerHTML = Main.editedPos;
         parent.childNodes[4].innerHTML = "<a href='javascript:;' onClick='Samples.ukarabati(\""+aliquot+"\", \"edit\")'>Edit</a>\n\
         <a href='javascript:;' onClick='Samples.ukarabati(\""+aliquot+"\", \"delete\")'>Delete</a>";   //restore the links
         message = "<div class='no_error'>The aliquot location has been successfully updated.<br />Enter a sample to aliquot.</div>";
      }
      else{
         message = "<div class='error'>"+data.error+'</div>';
         Samples.cancelEdits();
      }
      
      $('#search_mssg').html(message);
      $('#searchItemId').focus();
   },
   
   /**
    * Updates the interface depending on whether the aliquots were correctly edited or not
    */
   updateDeletes: function(data){
      var message, parent = Main.parent2delete;
      if(data.error == undefined){
         parent.childNodes[1].innerHTML = '&nbsp;';
         parent.childNodes[2].innerHTML = '&nbsp;';
         parent.childNodes[3].innerHTML = '&nbsp;';
         parent.childNodes[4].innerHTML = '&nbsp;';
         message = "<div class='no_error'>The aliquot location has been successfully deleted.<br />Enter a sample to aliquot.</div>";
         delete(Samples.aliquot2save);
         $('#search_mssg').html(message);
      }
      else{
         message = "<div class='error'>"+data.error+'</div>';
      }
      $('#searchItemId').focus();
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