<!doctype html>
<html>

<h1>Coucou</h1>


<style>
	span.done, span.todo {
		display: inline-block;
		width:18px;
		height:18px;
		border-radius: 5px;
		float:right;
	}
	span.done {
		background-color: #1ab4c8;
	}
	span.todo {
		background-color: #d4d4d4;
	}
	progress {
		display: block;
		width: 100%;
		height: 2em;
		margin: .5em 0 -5px 0;
		border-radius: 5px;
		background-color: #d4d4d4;
	}

	progress::-webkit-progress-bar {
		border-radius: 5px;
		background-color: #d4d4d4;
	}

	progress::-webkit-progress-value {
		border-radius: 5px;
		background-color: #1ab4c8;
		background-size: 40px 40px;
	}

	progress::-moz-progress-bar {
		border-radius: 5px;
		background-color: #1ab4c8;
		background-size: 40px 40px;
		-moz-animation: progress 8s linear infinite;
		animation: progress 8s linear infinite;
	}
</style>


</html>