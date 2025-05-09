<!-- Media Viewer Modal -->
<div id="mediaViewerModal" class="modal" style="display: none; background-color: rgba(0,0,0,0.85);">
    <span class="close-btn media-close-btn" style="color: #fff; font-size: 40px; top: 20px; right: 35px;">&times;</span>
    <div class="modal-content media-modal-content" 
         style="background: transparent; border: none; box-shadow: none; width: auto; padding: 0; display: flex; flex-direction: row;">
        
        <!-- Left Pane: Media Display -->
        <div class="modal-media-pane" style="flex: 2; display: flex; align-items: center; justify-content: center; background-color: #000; overflow: hidden; position: relative;">
            <img id="modalImage" src="" alt="Memory Image" style="display: none; max-width: 100%; max-height: 100%; object-fit: contain;">
            <video id="modalVideo" controls style="display: none; max-width: 100%; max-height: 100%; object-fit: contain;">
                <source id="modalVideoSource" src="" type="video/mp4">
                Your browser does not support the video tag.
            </video>
        </div>

        <!-- Right Pane: Interactions -->
        <div class="modal-interactions-pane" style="flex: 1; background: #fff; color: #333; padding: 20px; display: flex; flex-direction: column; overflow-y: auto;">
            <div class="memory-author-info" style="margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #eee;">
                <strong id="modalMemoryAuthor">Author Name</strong> 
                <span id="modalMemoryDate" style="font-size:0.8em; color:#777;">Date</span>
            </div>
            <div id="modalMemoryDescription" style="margin-bottom: 20px; font-size: 0.95em; line-height: 1.5; white-space: pre-wrap; word-wrap: break-word;">
                Description placeholder...
            </div>
            
            <div class="modal-reactions-summary" style="margin-bottom: 15px; padding-bottom:10px; border-bottom:1px solid #eee;">
                <!-- Reaction counts e.g., ❤️ 5 👍 12 --> 
            </div>
            <div class="modal-reaction-bar" style="margin-bottom: 20px; display:flex; gap:10px;">
                <button class="btn-like-action" data-reaction-type="like" style="padding: 8px 12px; border:1px solid #ddd; border-radius:20px; background: #f0f0f0; cursor:pointer;">👍 Like</button>
                <!-- Other reaction triggers can go here -->
            </div>

            <h4>Comments</h4>
            <div id="modalCommentsArea" style="flex-grow: 1; margin-bottom: 15px; overflow-y: auto;">
                <p class="no-comments-yet" style="color: #777;">No comments yet. Be the first!</p>
            </div>
            <form id="modalCommentForm" style="display: flex; gap: 10px; margin-top: auto;">
                <input type="hidden" id="modalMemoryId" name="memory_id" value="">
                <textarea name="comment_text" placeholder="Write a comment..." required style="flex-grow: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px; resize: vertical; min-height: 40px;"></textarea>
                <button type="submit" style="padding: 8px 15px; background-color: var(--primary-color); color: white; border: none; border-radius: 4px; cursor: pointer;">Post</button>
            </form>
            <div id="commentError" style="color: red; font-size: 0.9em; margin-top: 5px;"></div>
        </div>
    </div>
</div> 