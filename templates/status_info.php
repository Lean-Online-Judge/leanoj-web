<h2>Submission Status</h2>
<p>
  Below is a brief explanation of possible submission statuses. For details, see the checker <a target="_blank" href="https://github.com/Lean-Online-Judge/leanoj-checker">source code</a>.
</p>
<ul>
  <li>
  <p>
    <span class="status-pending">PENDING</span><br>
    The submission has not been judged yet. You may need to refresh the page to see if the status has been updated.
  </p>
  <li>
  <p>
    <span class="status-passed">PASSED</span><br>
    The submission has passed. Hooray!
  </p>
  <li>
  <p>
    <span class="status-cell">Blame author</span><br>
    The author has made a mistake when preparing the problem (or it appeared after a <code>Mathlib</code> upgrade). Please, <a target="_blank" href="https://discord.gg/a4xYPXXBxU">contact</a> the admin if you encounter this.
  </p>
  <li>
  <p>
    <span class="status-cell">Compilation Error</span><br>
    The submission compilation has failed. Make sure your <code>Mathlib</code> version matches the one used by the checker. It is normally the latest stable one (currently, v4.28.0).
  </p>
  <li>
  <p>
    <span class="status-cell">Environment error</span><br>
    This error is caused by attempts to modify the Lean environment via metaprogramming (presumably, to trick the checker). If that is your intention, there is a special <a href="index.php?action=view_problem&id=25">problem</a> for that. Some tactics may also cause this error (e.g., <code>native_decide</code>).
  </p>
  <li>
  <p>
    <span class="status-cell">Forbidden axiom</span><br>
    The solution uses axioms outside of <code>propext</code>, <code>Classical.choice</code>, and <code>Quot.sound</code>. Having unfilled <code>sorry</code> also causes this error (because it uses <code>sorryAx</code> under the hood). You can view the axioms used with the <code>#print axioms</code> macro in your Lean editor.
  </p>
  <li>
  <p>
    <span class="status-cell">Template mismatch</span><br>
    Some declarations in the template are missing or do not match those in the submission (up to definitional equality). Submissions can still use extra declarations.
  </p>
  <li>
  <p>
    <span class="status-cell">Bad answer</span><br>
    The provided answer is not (definitionally) equal to the one set for this problem. Make sure to use an answer from the <a href="index.php?action=view_answers">Answer Bank</a>.
  </p>
  <li>
  <p>
    <span class="status-cell">Time out</span><br>
    Judging the submission took more time than the predefined limit (generous 300 seconds). This might be caused by heavy imports (e.g., whole <code>Mathlib</code>). The <code>#min_imports</code> macro can be helpful to slim them down.
  </p>
  <li>
  <p>
    <span class="status-cell">Out of memory</span><br>
    Judging the submission took more memory than the predefined limit (around 4 gigabytes).
  </p>
  <li>
  <p>
    <span class="status-cell">Unknown error</span><br>
    The cause of the error is unknown. Please, <a target="_blank" href="https://discord.gg/a4xYPXXBxU">contact</a> the admin if you encounter this.
  </p>
  </p>
</ul>
