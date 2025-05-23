import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClientModule } from '@angular/common/http';
import { AiChatWidgetComponent } from './ai-chat-widget/ai-chat-widget.component';

@NgModule({
  declarations: [
    AiChatWidgetComponent
  ],
  imports: [
    CommonModule,
    FormsModule,
    HttpClientModule
  ],
  exports: [
    AiChatWidgetComponent
  ]
})
export class AiAssistantModule { }
